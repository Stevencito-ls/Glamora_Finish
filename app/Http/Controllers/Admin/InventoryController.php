<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\WarehouseLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');
        $expiryWindow = (string) $request->query('expiry_window', '');
        $locationId = $request->query('location_id');
        $today = now()->toDateString();

        $batches = ProductBatch::query()
            ->with(['variant.product.brand', 'warehouseLocation'])
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($locationId !== null && $locationId !== '', fn ($q) => $q->where('warehouse_location_id', (int) $locationId))
            ->when($expiryWindow === 'critical_30', function ($q) use ($today): void {
                $q->where('status', 'active')
                    ->where('current_quantity', '>', 0)
                    ->whereBetween('expiration_date', [$today, now()->addDays(30)->toDateString()]);
            })
            ->when($expiryWindow === 'soon_60', function ($q) use ($today): void {
                $q->where('status', 'active')
                    ->where('current_quantity', '>', 0)
                    ->whereBetween('expiration_date', [$today, now()->addDays(60)->toDateString()]);
            })
            ->when($expiryWindow === 'expired', function ($q) use ($today): void {
                $q->where(function ($sub) use ($today): void {
                    $sub->where('status', 'expired')
                        ->orWhereDate('expiration_date', '<=', $today);
                });
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('batch_number', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhereHas('variant', function ($vq) use ($search): void {
                            $vq->where('sku', 'like', "%{$search}%")
                                ->orWhere('shade_name', 'like', "%{$search}%")
                                ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->orderBy('expiration_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        // Variant stock summary & reorder alerts (ONLY_FULL_GROUP_BY safe)
        $stockByVariant = DB::table('product_batches')
            ->where('status', 'active')
            ->whereDate('expiration_date', '>', $today)
            ->groupBy('product_variant_id')
            ->selectRaw('product_variant_id, COALESCE(SUM(current_quantity), 0) as physical_stock, COALESCE(SUM(reserved_quantity), 0) as reserved_stock, COALESCE(SUM(CASE WHEN current_quantity > reserved_quantity THEN current_quantity - reserved_quantity ELSE 0 END), 0) as available_stock')
            ->get()
            ->keyBy('product_variant_id');

        $allVariants = ProductVariant::query()
            ->with(['product.brand', 'product.category'])
            ->whereHas('product', fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('sku')
            ->get();

        $reorderAlerts = $allVariants
            ->map(function (ProductVariant $variant) use ($stockByVariant): array {
                $agg = $stockByVariant->get($variant->id);
                $available = (int) ($agg->available_stock ?? 0);
                $physical = (int) ($agg->physical_stock ?? 0);
                $reserved = (int) ($agg->reserved_stock ?? 0);

                return [
                    'variant' => $variant,
                    'available' => $available,
                    'physical' => $physical,
                    'reserved' => $reserved,
                    'is_critical' => $available <= (int) ($variant->product?->min_stock_alert ?? 0),
                    'needs_reorder' => $available <= (int) ($variant->product?->reorder_point ?? 0),
                ];
            })
            ->filter(fn (array $row): bool => $row['needs_reorder'])
            ->sortBy('available')
            ->values();

        $summaryCounts = [
            'total_available' => (int) $stockByVariant->sum('available_stock'),
            'total_reserved' => (int) $stockByVariant->sum('reserved_stock'),
            'expiring_60' => (int) ProductBatch::query()->expiringSoon(60)->reorder()->count(),
            'critical_30' => (int) ProductBatch::query()->expiringSoon(30)->reorder()->count(),
            'quarantine' => (int) ProductBatch::query()->where('status', 'quarantine')->count(),
            'reorder_variants' => $reorderAlerts->count(),
        ];

        $locations = WarehouseLocation::query()->orderBy('code')->get();

        return view('admin.inventory.index', [
            'batches' => $batches,
            'locations' => $locations,
            'variants' => $allVariants,
            'reorderAlerts' => $reorderAlerts,
            'summaryCounts' => $summaryCounts,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'expiryWindow' => $expiryWindow,
            'locationId' => $locationId,
        ]);
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $request->merge([
            'batch_number' => strtoupper(trim((string) $request->input('batch_number', ''))),
        ]);

        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'warehouse_location_id' => ['required', 'integer', 'exists:warehouse_locations,id'],
            'batch_number' => ['required', 'string', 'min:3', 'max:80', 'unique:product_batches,batch_number'],
            'manufacturing_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expiration_date' => ['required', 'date', 'after:today'],
            'initial_quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999'],
            'supplier_name' => ['required', 'string', 'min:2', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'batch_number.unique' => 'El número de lote ya existe en el sistema.',
            'expiration_date.after' => 'La fecha de vencimiento debe ser posterior a hoy para ingresar stock vendible.',
            'initial_quantity.min' => 'La cantidad ingresada debe ser al menos 1 unidad.',
        ]);

        if (! empty($validated['manufacturing_date']) && $validated['manufacturing_date'] >= $validated['expiration_date']) {
            return back()
                ->withInput()
                ->withErrors(['expiration_date' => 'La fecha de vencimiento debe ser posterior a la fecha de fabricación.']);
        }

        /** @var ProductBatch $batch */
        $batch = DB::transaction(function () use ($validated): ProductBatch {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail((int) $validated['product_variant_id']);
            /** @var WarehouseLocation $location */
            $location = WarehouseLocation::query()->findOrFail((int) $validated['warehouse_location_id']);

            $qty = (int) $validated['initial_quantity'];
            $unitCost = round((float) $validated['unit_cost'], 2);

            $batch = ProductBatch::query()->create([
                'product_variant_id' => $variant->id,
                'warehouse_location_id' => $location->id,
                'batch_number' => strtoupper(trim($validated['batch_number'])),
                'manufacturing_date' => $validated['manufacturing_date'] ?: null,
                'expiration_date' => $validated['expiration_date'],
                'initial_quantity' => $qty,
                'current_quantity' => $qty,
                'reserved_quantity' => 0,
                'unit_cost' => $unitCost,
                'supplier_name' => trim($validated['supplier_name']),
                'status' => 'active',
                'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== '' ? trim((string) $validated['notes']) : null,
            ]);

            $variant->unsetRelation('batches');
            $balanceAfter = $variant->availableStock();

            InventoryMovement::query()->create([
                'product_variant_id' => $variant->id,
                'product_batch_id' => $batch->id,
                'warehouse_location_id' => $location->id,
                'user_id' => Auth::id(),
                'movement_type' => 'entrada',
                'quantity' => $qty,
                'balance_after' => $balanceAfter,
                'unit_cost' => $unitCost,
                'reference_type' => ProductBatch::class,
                'reference_id' => $batch->id,
                'notes' => "Ingreso de nuevo lote {$batch->batch_number} en {$location->code} (Proveedor: {$batch->supplier_name}).",
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'inventory_batch_created',
                'info',
                "Lote {$batch->batch_number} ({$qty} uds. de {$variant->sku}) ingresado a almacén {$location->code}.",
                [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'sku' => $variant->sku,
                    'quantity' => $qty,
                    'location_code' => $location->code,
                ],
            );

            return $batch;
        });

        return redirect()
            ->route('admin.inventory.index')
            ->with('admin_success', "Lote {$batch->batch_number} ingresado con {$batch->initial_quantity} unidades y registrado en Kardex.");
    }

    public function adjustBatch(Request $request, ProductBatch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in([
                'adjustment_in',
                'adjustment_out',
                'quarantine',
                'release_quarantine',
                'mark_expired',
                'relocate',
            ])],
            'quantity_delta' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Debes indicar un motivo auditable para el ajuste de lote.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $action = $validated['action'];
        if (in_array($action, ['adjustment_in', 'adjustment_out'], true) && empty($validated['quantity_delta'])) {
            return back()->withErrors(['quantity_delta' => 'Ingresa la cantidad de unidades para el ajuste de stock.']);
        }

        if ($action === 'relocate' && empty($validated['warehouse_location_id'])) {
            return back()->withErrors(['warehouse_location_id' => 'Selecciona la nueva ubicación de almacén.']);
        }

        DB::transaction(function () use ($batch, $validated, $action): void {
            /** @var ProductBatch $lockedBatch */
            $lockedBatch = ProductBatch::query()->lockForUpdate()->findOrFail($batch->id);
            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($lockedBatch->product_variant_id);

            $delta = (int) ($validated['quantity_delta'] ?? 0);
            $reason = trim($validated['reason']);
            $newLocationId = ! empty($validated['warehouse_location_id'])
                ? (int) $validated['warehouse_location_id']
                : $lockedBatch->warehouse_location_id;

            $today = now()->toDateString();
            $movementType = 'ajuste';
            $movementQty = 0;
            $severity = 'info';

            switch ($action) {
                case 'adjustment_in':
                    if ($lockedBatch->status === 'expired') {
                        throw ValidationException::withMessages([
                            'action' => 'No se pueden ingresar unidades a un lote dado de baja por vencimiento.',
                        ]);
                    }
                    $lockedBatch->current_quantity = (int) $lockedBatch->current_quantity + $delta;
                    if (
                        $lockedBatch->status === 'depleted'
                        && $lockedBatch->expiration_date !== null
                        && $lockedBatch->expiration_date->toDateString() > $today
                    ) {
                        $lockedBatch->status = 'active';
                    }
                    $movementType = 'ajuste';
                    $movementQty = $delta;
                    $notePrefix = "Ajuste positivo (+{$delta} uds.) en lote {$lockedBatch->batch_number}";
                    break;

                case 'adjustment_out':
                    $newCurrent = (int) $lockedBatch->current_quantity - $delta;
                    if ($newCurrent < (int) $lockedBatch->reserved_quantity) {
                        throw ValidationException::withMessages([
                            'quantity_delta' => "No se puede reducir por debajo de las {$lockedBatch->reserved_quantity} unidad(es) reservadas por pedidos.",
                        ]);
                    }
                    $lockedBatch->current_quantity = $newCurrent;
                    if ($lockedBatch->current_quantity <= 0) {
                        $lockedBatch->status = 'depleted';
                    }
                    $movementType = 'ajuste';
                    $movementQty = -$delta;
                    $severity = 'warning';
                    $notePrefix = "Ajuste negativo (-{$delta} uds.) en lote {$lockedBatch->batch_number}";
                    break;

                case 'quarantine':
                    $lockedBatch->status = 'quarantine';
                    $lockedBatch->warehouse_location_id = $newLocationId;
                    $movementType = 'cuarentena';
                    $movementQty = 0;
                    $severity = 'warning';
                    $notePrefix = "Lote {$lockedBatch->batch_number} puesto en CUARENTENA sanitaria";
                    break;

                case 'release_quarantine':
                    if ($lockedBatch->status !== 'quarantine') {
                        throw ValidationException::withMessages([
                            'action' => 'Solo los lotes en cuarentena sanitaria pueden liberarse con esta acción.',
                        ]);
                    }
                    if ($lockedBatch->expiration_date === null || $lockedBatch->expiration_date->toDateString() <= $today) {
                        throw ValidationException::withMessages([
                            'action' => 'No se puede liberar de cuarentena un lote vencido.',
                        ]);
                    }
                    $lockedBatch->status = $lockedBatch->current_quantity > 0 ? 'active' : 'depleted';
                    $lockedBatch->warehouse_location_id = $newLocationId;
                    $movementType = 'ajuste';
                    $movementQty = 0;
                    $notePrefix = "Lote {$lockedBatch->batch_number} liberado de cuarentena a estado activo";
                    break;

                case 'mark_expired':
                    if ((int) $lockedBatch->reserved_quantity > 0) {
                        throw ValidationException::withMessages([
                            'action' => 'Libera o cancela primero las reservas activas de este lote antes de marcarlo como vencido.',
                        ]);
                    }
                    $lockedBatch->status = 'expired';
                    $movementType = 'merma';
                    $movementQty = 0;
                    $severity = 'warning';
                    $notePrefix = "Lote {$lockedBatch->batch_number} dado de baja por vencimiento/merma";
                    break;

                case 'relocate':
                    $lockedBatch->warehouse_location_id = $newLocationId;
                    $movementType = 'reubicacion';
                    $movementQty = 0;
                    $notePrefix = "Reubicación de lote {$lockedBatch->batch_number}";
                    break;

                default:
                    $notePrefix = "Movimiento en lote {$lockedBatch->batch_number}";
                    break;
            }

            $lockedBatch->notes = $reason;
            $lockedBatch->save();

            $variant->unsetRelation('batches');
            $balanceAfter = $variant->availableStock();

            InventoryMovement::query()->create([
                'product_variant_id' => $variant->id,
                'product_batch_id' => $lockedBatch->id,
                'warehouse_location_id' => $lockedBatch->warehouse_location_id,
                'user_id' => Auth::id(),
                'movement_type' => $movementType,
                'quantity' => $movementQty,
                'balance_after' => $balanceAfter,
                'unit_cost' => $lockedBatch->unit_cost,
                'reference_type' => ProductBatch::class,
                'reference_id' => $lockedBatch->id,
                'notes' => "{$notePrefix}. Motivo: {$reason}",
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'inventory_batch_adjusted',
                $severity,
                "{$notePrefix} ({$variant->sku}): {$reason}",
                [
                    'batch_id' => $lockedBatch->id,
                    'batch_number' => $lockedBatch->batch_number,
                    'action' => $action,
                    'quantity_delta' => $movementQty,
                    'balance_after' => $balanceAfter,
                    'new_status' => $lockedBatch->status,
                ],
            );
        });

        return redirect()
            ->route('admin.inventory.index')
            ->with('admin_success', "Lote {$batch->batch_number} actualizado y auditado en Kardex.");
    }

    public function locationsIndex(): View
    {
        $locations = WarehouseLocation::query()
            ->withCount('batches')
            ->orderBy('code')
            ->paginate(20);

        return view('admin.inventory.locations', [
            'locations' => $locations,
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:50', 'unique:warehouse_locations,code'],
            'zone' => ['required', 'string', 'max:60'],
            'aisle' => ['required', 'string', 'max:30'],
            'rack' => ['required', 'string', 'max:30'],
            'shelf' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
            'temperature_controlled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.unique' => 'El código de ubicación ya existe.',
        ]);

        $location = WarehouseLocation::query()->create([
            'code' => strtoupper(trim($validated['code'])),
            'zone' => trim($validated['zone']),
            'aisle' => strtoupper(trim($validated['aisle'])),
            'rack' => strtoupper(trim($validated['rack'])),
            'shelf' => strtoupper(trim($validated['shelf'])),
            'description' => $validated['description'] ?? null,
            'temperature_controlled' => $request->boolean('temperature_controlled'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        SecurityActionTriggered::dispatch(
            Auth::user(),
            'warehouse_location_created',
            'info',
            "Ubicación de almacén {$location->code} ({$location->zone}) creada.",
            ['location_id' => $location->id, 'code' => $location->code],
        );

        return redirect()
            ->route('admin.inventory.locations.index')
            ->with('admin_success', "Ubicación {$location->code} creada correctamente.");
    }

    public function updateLocation(Request $request, WarehouseLocation $location): RedirectResponse
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:50', Rule::unique('warehouse_locations', 'code')->ignore($location->id)],
            'zone' => ['required', 'string', 'max:60'],
            'aisle' => ['required', 'string', 'max:30'],
            'rack' => ['required', 'string', 'max:30'],
            'shelf' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
            'temperature_controlled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $location->update([
            'code' => strtoupper(trim($validated['code'])),
            'zone' => trim($validated['zone']),
            'aisle' => strtoupper(trim($validated['aisle'])),
            'rack' => strtoupper(trim($validated['rack'])),
            'shelf' => strtoupper(trim($validated['shelf'])),
            'description' => $validated['description'] ?? null,
            'temperature_controlled' => $request->boolean('temperature_controlled'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.inventory.locations.index')
            ->with('admin_success', "Ubicación {$location->code} actualizada.");
    }

    public function destroyLocation(WarehouseLocation $location): RedirectResponse
    {
        if ($location->batches()->exists() || $location->inventoryMovements()->exists()) {
            return back()->with('admin_error', "No se puede eliminar {$location->code} porque registra lotes o movimientos en Kardex. Desactívala en su lugar.");
        }

        $code = $location->code;
        $location->delete();

        return redirect()
            ->route('admin.inventory.locations.index')
            ->with('admin_success', "Ubicación {$code} eliminada.");
    }

    public function kardex(Request $request): View
    {
        $variantId = $request->query('variant_id');
        $batchId = $request->query('batch_id');
        $movementType = (string) $request->query('movement_type', '');
        $dateFromRaw = trim((string) $request->query('date_from', ''));
        $dateToRaw = trim((string) $request->query('date_to', ''));
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromRaw) === 1 ? $dateFromRaw : '';
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToRaw) === 1 ? $dateToRaw : '';
        $search = trim((string) $request->query('q', ''));

        $movements = InventoryMovement::query()
            ->with(['variant.product', 'batch', 'warehouseLocation', 'user'])
            ->when($variantId !== null && $variantId !== '', fn ($q) => $q->where('product_variant_id', (int) $variantId))
            ->when($batchId !== null && $batchId !== '', fn ($q) => $q->where('product_batch_id', (int) $batchId))
            ->when($movementType !== '', fn ($q) => $q->where('movement_type', $movementType))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('variant', fn ($vq) => $vq->where('sku', 'like', "%{$search}%")->orWhere('shade_name', 'like', "%{$search}%"))
                        ->orWhereHas('batch', fn ($bq) => $bq->where('batch_number', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $variants = ProductVariant::query()->with('product')->orderBy('sku')->get();
        $batches = ProductBatch::query()->orderByDesc('id')->limit(100)->get();

        $dbTypes = InventoryMovement::query()
            ->reorder()
            ->select('movement_type')
            ->distinct()
            ->orderBy('movement_type')
            ->pluck('movement_type')
            ->all();

        $movementTypes = array_values(array_unique(array_merge([
            'entrada',
            'reserva',
            'salida',
            'liberacion',
            'devolucion',
            'ajuste',
            'cuarentena',
            'merma',
            'reubicacion',
        ], $dbTypes)));

        return view('admin.inventory.kardex', [
            'movements' => $movements,
            'variants' => $variants,
            'batches' => $batches,
            'movementTypes' => $movementTypes,
            'variantId' => $variantId,
            'batchId' => $batchId,
            'movementType' => $movementType,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
        ]);
    }
}
