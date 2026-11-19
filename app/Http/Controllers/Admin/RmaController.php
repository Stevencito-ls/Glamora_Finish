<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReturnCondition;
use App\Events\SecurityActionTriggered;
use App\Exceptions\InsufficientFefoStockException;
use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Services\FefoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RmaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', OrderReturn::class);

        /** @var User $user */
        $user = Auth::user();
        $isOpsStaff = $user->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero']);

        $statusFilter = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));

        $baseQuery = OrderReturn::query();
        if (! $isOpsStaff) {
            $baseQuery->where('user_id', $user->id);
        }

        $statusCounts = (clone $baseQuery)
            ->reorder()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        $returns = (clone $baseQuery)
            ->with(['order', 'inspector', 'items.orderItem'])
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('rma_number', 'like', "%{$search}%")
                        ->orWhere('requester_name', 'like', "%{$search}%")
                        ->orWhere('requester_email', 'like', "%{$search}%")
                        ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(18)
            ->withQueryString();

        return view('admin.rma.index', [
            'returns' => $returns,
            'statusCounts' => $statusCounts,
            'statusFilter' => $statusFilter,
            'search' => $search,
        ]);
    }

    public function show(OrderReturn $orderReturn): View
    {
        $this->authorize('view', $orderReturn);

        $orderReturn->load([
            'order.payments',
            'user',
            'inspector',
            'items.orderItem',
            'items.variant.product',
            'items.batch.warehouseLocation',
        ]);

        $maxRefundable = (float) $orderReturn->items->sum(
            fn (ReturnItem $item): float => (float) ($item->orderItem?->unit_price ?? 0) * (int) $item->quantity
        );
        $orderTotal = (float) ($orderReturn->order?->total ?? $maxRefundable);
        $suggestedRefund = min($maxRefundable, $orderTotal);

        return view('admin.rma.show', [
            'orderReturn' => $orderReturn,
            'conditions' => ReturnCondition::cases(),
            'suggestedRefund' => round($suggestedRefund, 2),
        ]);
    }

    public function inspect(Request $request, OrderReturn $orderReturn, FefoService $fefoService): RedirectResponse
    {
        $this->authorize('inspect', $orderReturn);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.condition' => ['required', 'string', Rule::in(ReturnCondition::values())],
            'items.*.inspection_notes' => ['nullable', 'string', 'max:500'],
            'resolution_status' => ['nullable', 'string', Rule::in(['en_revision', 'resuelto', 'rechazado'])],
            'refund_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'Clasifica al menos un ítem de la devolución.',
            'items.*.condition.required' => 'Selecciona la condición sanitaria para cada ítem devuelto.',
            'items.*.condition.in' => 'Condición de inspección RMA inválida.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $requestedResolution = (string) ($validated['resolution_status'] ?? 'en_revision');

        if (in_array($requestedResolution, ['resuelto', 'rechazado'], true) && ! $actor->can('returns.resolve')) {
            abort(403, 'Solo Administración o SuperAdmin pueden emitir la resolución financiera final del RMA.');
        }

        try {
            $restockedCount = 0;

            DB::transaction(function () use ($orderReturn, $validated, $actor, $requestedResolution, $fefoService, &$restockedCount): void {
                /** @var OrderReturn $lockedReturn */
                $lockedReturn = OrderReturn::query()
                    ->with(['items.variant', 'items.orderItem', 'order'])
                    ->lockForUpdate()
                    ->findOrFail($orderReturn->id);

                if (in_array($lockedReturn->status, ['resuelto', 'rechazado'], true)) {
                    throw ValidationException::withMessages([
                        'rma' => "El RMA {$lockedReturn->rma_number} ya fue cerrado como «{$lockedReturn->status}».",
                    ]);
                }

                $orderTotal = (float) ($lockedReturn->order?->total ?? 0);
                $refundAmount = round((float) ($validated['refund_amount'] ?? 0), 2);

                if ($requestedResolution === 'resuelto' && $refundAmount > $orderTotal + 0.01) {
                    throw ValidationException::withMessages([
                        'refund_amount' => 'El monto de reembolso (S/ ' . number_format($refundAmount, 2) . ') no puede superar el total del pedido (S/ ' . number_format($orderTotal, 2) . ').',
                    ]);
                }

                $processedItems = 0;

                foreach ($lockedReturn->items as $item) {
                    $inputRow = $validated['items'][$item->id] ?? null;
                    if (! is_array($inputRow)) {
                        continue;
                    }

                    /** @var ReturnItem $lockedItem */
                    $lockedItem = ReturnItem::query()
                        ->with(['variant', 'orderItem'])
                        ->lockForUpdate()
                        ->findOrFail($item->id);

                    $condition = ReturnCondition::from($inputRow['condition']);

                    if ($lockedItem->restocked && $condition !== ReturnCondition::Reventa) {
                        throw ValidationException::withMessages([
                            "items.{$lockedItem->id}.condition" => 'Este ítem ya fue reintegrado al inventario FEFO como apto para reventa y su condición no puede modificarse.',
                        ]);
                    }

                    $lockedItem->condition = $condition;
                    $lockedItem->inspection_notes = isset($inputRow['inspection_notes']) && trim((string) $inputRow['inspection_notes']) !== ''
                        ? trim((string) $inputRow['inspection_notes'])
                        : null;
                    $lockedItem->save();

                    if ($condition->shouldRestock() && ! $lockedItem->restocked) {
                        $fefoService->restockFromReturn($lockedReturn, $lockedItem, $actor);
                        $restockedCount++;
                    }

                    $processedItems++;
                }

                if ($processedItems === 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Debes evaluar al menos un ítem perteneciente a esta solicitud RMA.',
                    ]);
                }

                if ($requestedResolution === 'resuelto') {
                    $lockedReturn->load('items');
                    if ($lockedReturn->items->contains(fn (ReturnItem $i): bool => $i->condition === null)) {
                        throw ValidationException::withMessages([
                            'items' => 'Clasifica la condición sanitaria de todos los ítems antes de aprobar la resolución final.',
                        ]);
                    }
                }

                $lockedReturn->inspector_id = $actor->id;
                $lockedReturn->inspected_at = now();
                $lockedReturn->status = in_array($requestedResolution, ['en_revision', 'resuelto', 'rechazado'], true)
                    ? $requestedResolution
                    : 'en_revision';

                if (in_array($lockedReturn->status, ['resuelto', 'rechazado'], true)) {
                    $lockedReturn->refund_amount = $lockedReturn->status === 'resuelto' ? $refundAmount : 0.00;
                }

                if (isset($validated['resolution_notes']) && trim((string) $validated['resolution_notes']) !== '') {
                    $lockedReturn->resolution_notes = trim((string) $validated['resolution_notes']);
                }

                $lockedReturn->save();

                SecurityActionTriggered::dispatch(
                    $actor,
                    $lockedReturn->status === 'resuelto' ? 'rma_resolved' : 'rma_inspected',
                    'info',
                    "Inspección sanitaria de {$lockedReturn->rma_number} completada (estado: {$lockedReturn->status}, reintegrados: {$restockedCount}).",
                    [
                        'return_id' => $lockedReturn->id,
                        'rma_number' => $lockedReturn->rma_number,
                        'status' => $lockedReturn->status,
                        'restocked_items' => $restockedCount,
                        'refund_amount' => (float) $lockedReturn->refund_amount,
                    ],
                );
            });
        } catch (InsufficientFefoStockException $e) {
            return back()->withInput()->with('admin_error', $e->getMessage());
        }

        return redirect()
            ->route('admin.rma.show', $orderReturn)
            ->with('admin_success', "Inspección de {$orderReturn->rma_number} registrada correctamente.");
    }

    public function resolve(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->authorize('resolve', $orderReturn);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['resuelto', 'rechazado'])],
            'refund_amount' => ['required_if:status,resuelto', 'nullable', 'numeric', 'min:0', 'max:999999'],
            'resolution_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'resolution_notes.required' => 'Ingresa las notas finales de resolución del RMA.',
            'resolution_notes.min' => 'Las notas de resolución deben tener al menos 5 caracteres.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        DB::transaction(function () use ($orderReturn, $validated, $actor): void {
            /** @var OrderReturn $lockedReturn */
            $lockedReturn = OrderReturn::query()
                ->with(['items', 'order'])
                ->lockForUpdate()
                ->findOrFail($orderReturn->id);

            if (in_array($lockedReturn->status, ['resuelto', 'rechazado'], true)) {
                throw ValidationException::withMessages([
                    'status' => "El RMA {$lockedReturn->rma_number} ya se encuentra cerrado ({$lockedReturn->status}).",
                ]);
            }

            if ($validated['status'] === 'resuelto') {
                $hasUninspected = $lockedReturn->items->contains(fn (ReturnItem $i): bool => $i->condition === null);
                if ($hasUninspected) {
                    throw ValidationException::withMessages([
                        'status' => 'Inspecciona y clasifica todos los ítems antes de aprobar la resolución final del RMA.',
                    ]);
                }

                $orderTotal = (float) ($lockedReturn->order?->total ?? 0);
                $refundAmount = round((float) ($validated['refund_amount'] ?? 0), 2);
                if ($refundAmount > $orderTotal + 0.01) {
                    throw ValidationException::withMessages([
                        'refund_amount' => 'El reembolso no puede exceder el total del pedido.',
                    ]);
                }
            } else {
                $refundAmount = 0.00;
            }

            $lockedReturn->update([
                'status' => $validated['status'],
                'refund_amount' => $refundAmount,
                'resolution_notes' => trim($validated['resolution_notes']),
                'inspector_id' => $lockedReturn->inspector_id ?? $actor->id,
                'inspected_at' => $lockedReturn->inspected_at ?? now(),
            ]);

            SecurityActionTriggered::dispatch(
                $actor,
                'rma_resolved',
                $validated['status'] === 'rechazado' ? 'warning' : 'info',
                "RMA {$lockedReturn->rma_number} finalizado como «{$validated['status']}» (reembolso S/ " . number_format($refundAmount, 2) . ').',
                [
                    'return_id' => $lockedReturn->id,
                    'rma_number' => $lockedReturn->rma_number,
                    'status' => $validated['status'],
                    'refund_amount' => $refundAmount,
                ],
            );
        });

        return redirect()
            ->route('admin.rma.show', $orderReturn)
            ->with('admin_success', "RMA {$orderReturn->rma_number} resuelto correctamente.");
    }
}
