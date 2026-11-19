<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackingController extends Controller
{
    public function index(Request $request): View
    {
        $searchCode = strtoupper(trim((string) $request->query('codigo', '')));
        $order = null;
        $searched = $searchCode !== '';

        if ($searched) {
            $order = Order::query()
                ->with([
                    'items.variant.product',
                    'items.returnItems.orderReturn',
                    'payments',
                    'returns.items.orderItem',
                ])
                ->where(function ($q) use ($searchCode): void {
                    $q->whereRaw('UPPER(order_number) = ?', [$searchCode])
                        ->orWhereRaw('UPPER(tracking_code) = ?', [$searchCode]);
                })
                ->first();
        }

        $timelineSteps = [
            OrderStatus::Cotizacion,
            OrderStatus::PendientePago,
            OrderStatus::PagoVerificado,
            OrderStatus::EnPicking,
            OrderStatus::EnPacking,
            OrderStatus::Despachado,
            OrderStatus::Entregado,
        ];

        $privacySummary = $order !== null ? [
            'recipient' => $this->maskName($order->customer_name),
            'email' => $this->maskEmail($order->customer_email),
            'phone' => $this->maskPhone($order->customer_phone),
            'address' => "Dirección protegida · {$order->shipping_district}, {$order->shipping_city}",
            'document' => $this->maskDocument($order->document_type, $order->document_number),
            'payment_refs' => $order->payments
                ->mapWithKeys(fn ($p): array => [$p->id => $this->maskReference($p->transaction_reference)])
                ->all(),
        ] : null;

        return view('storefront.tracking.index', [
            'searchCode' => $searchCode,
            'searched' => $searched,
            'order' => $order,
            'privacySummary' => $privacySummary,
            'timelineSteps' => $timelineSteps,
        ]);
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:64'],
            'requester_email' => ['required', 'email:rfc', 'max:150'],
            'requester_name' => ['nullable', 'string', 'max:150'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.selected' => ['nullable'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sku' => ['nullable', 'string', 'max:80'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'order_number.required' => 'Ingresa el código o número de pedido (ej. GLM-2026-0001).',
            'requester_email.required' => 'Ingresa el correo electrónico con el que realizaste el pedido.',
            'requester_email.email' => 'Ingresa un correo electrónico válido.',
            'reason.required' => 'Describe el motivo de la devolución o cambio.',
            'reason.min' => 'Detalla el motivo con al menos 10 caracteres.',
        ]);

        $code = strtoupper(trim($validated['order_number']));
        $email = mb_strtolower(trim($validated['requester_email']));

        /** @var array{order: Order, rma: OrderReturn} $result */
        $result = DB::transaction(function () use ($code, $email, $validated): array {
            $order = Order::query()
                ->where(function ($q) use ($code): void {
                    $q->whereRaw('UPPER(order_number) = ?', [$code])
                        ->orWhereRaw('UPPER(tracking_code) = ?', [$code]);
                })
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Order) {
                throw ValidationException::withMessages([
                    'order_number' => "No encontramos ningún pedido con el código {$code}.",
                ]);
            }

            if (mb_strtolower(trim($order->customer_email)) !== $email) {
                throw ValidationException::withMessages([
                    'requester_email' => 'El correo electrónico ingresado no coincide con el comprador del pedido.',
                ]);
            }

            if (in_array($order->status, [OrderStatus::Cotizacion, OrderStatus::PendientePago, OrderStatus::Cancelado], true)) {
                throw ValidationException::withMessages([
                    'order_number' => "El pedido {$order->order_number} está en estado «{$order->status->label()}» y aún no califica para devolución RMA.",
                ]);
            }

            $order->load(['items.returnItems.orderReturn']);

            $selectedItemsPayload = $this->resolveSelectedOrderItems($order, $validated);

            if ($selectedItemsPayload === []) {
                throw ValidationException::withMessages([
                    'items' => 'Selecciona al menos un producto y una cantidad válida disponible para devolución.',
                ]);
            }

            $rmaNumber = $this->generateUniqueRmaNumber();

            $orderReturn = OrderReturn::query()->create([
                'rma_number' => $rmaNumber,
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'requester_name' => isset($validated['requester_name']) && trim((string) $validated['requester_name']) !== ''
                    ? trim((string) $validated['requester_name'])
                    : $order->customer_name,
                'requester_email' => $email,
                'reason' => trim($validated['reason']),
                'status' => 'solicitado',
                'refund_amount' => 0.00,
            ]);

            foreach ($selectedItemsPayload as $line) {
                /** @var OrderItem $orderItem */
                $orderItem = $line['order_item'];

                ReturnItem::query()->create([
                    'return_id' => $orderReturn->id,
                    'order_item_id' => $orderItem->id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'product_batch_id' => $orderItem->product_batch_id,
                    'quantity' => $line['quantity'],
                    'condition' => null,
                    'restocked' => false,
                ]);
            }

            return [
                'order' => $order,
                'rma' => $orderReturn,
            ];
        });

        $order = $result['order'];
        $rma = $result['rma'];

        return redirect()
            ->route('tracking.index', ['codigo' => $order->order_number])
            ->withFragment('solicitud-rma')
            ->with('rma_success', "Solicitud registrada correctamente. Número asignado: {$rma->rma_number}. Nuestro equipo de control sanitario inspeccionará los ítems en zona ALM-CUAR-E01.")
            ->with('generated_rma_number', $rma->rma_number);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{order_item: OrderItem, quantity: int}>
     */
    private function resolveSelectedOrderItems(Order $order, array $validated): array
    {
        $orderItemsById = $order->items->keyBy('id');
        $resolved = [];

        $itemsInput = $validated['items'] ?? null;
        if (is_array($itemsInput) && $itemsInput !== []) {
            $hadSelectedMismatch = false;

            foreach ($itemsInput as $orderItemId => $row) {
                if (! is_array($row) || empty($row['selected'])) {
                    continue;
                }

                $orderItem = $orderItemsById->get((int) $orderItemId);
                if (! $orderItem instanceof OrderItem) {
                    $hadSelectedMismatch = true;
                    continue;
                }

                $qty = (int) ($row['quantity'] ?? 1);
                $maxReturnable = $this->maxReturnableQuantity($orderItem);

                if ($maxReturnable < 1) {
                    throw ValidationException::withMessages([
                        'items' => "El producto {$orderItem->sku} ya no tiene unidades disponibles para devolución.",
                    ]);
                }

                if ($qty < 1 || $qty > $maxReturnable) {
                    throw ValidationException::withMessages([
                        'items' => "La cantidad a devolver para {$orderItem->sku} debe estar entre 1 y {$maxReturnable} unidad(es) disponible(s).",
                    ]);
                }

                $resolved[] = [
                    'order_item' => $orderItem,
                    'quantity' => $qty,
                ];
            }

            if ($resolved === [] && $hadSelectedMismatch) {
                throw ValidationException::withMessages([
                    'items' => 'Los productos seleccionados no corresponden al pedido indicado. Consulta primero el pedido para actualizar la lista.',
                ]);
            }

            return $resolved;
        }

        // Fallback para envíos directos por SKU o primer ítem disponible si el buscador no estaba pre-cargado
        $sku = strtoupper(trim((string) ($validated['sku'] ?? '')));
        $requestedQty = max(1, (int) ($validated['quantity'] ?? 1));

        $targetItem = $sku !== ''
            ? $order->items->first(fn (OrderItem $item): bool => strtoupper($item->sku) === $sku)
            : $order->items->first(fn (OrderItem $item): bool => $this->maxReturnableQuantity($item) > 0);

        if (! $targetItem instanceof OrderItem) {
            if ($sku !== '') {
                throw ValidationException::withMessages([
                    'sku' => "El SKU {$sku} no pertenece al pedido {$order->order_number}.",
                ]);
            }

            return [];
        }

        $maxReturnable = $this->maxReturnableQuantity($targetItem);
        if ($maxReturnable < 1 || $requestedQty > $maxReturnable) {
            throw ValidationException::withMessages([
                'quantity' => "Cantidad máxima retornable para {$targetItem->sku}: {$maxReturnable} unidad(es).",
            ]);
        }

        return [[
            'order_item' => $targetItem,
            'quantity' => $requestedQty,
        ]];
    }

    private function maskName(?string $name): string
    {
        $clean = trim((string) $name);
        if ($clean === '') {
            return 'Cliente verificado';
        }

        $parts = preg_split('/\s+/', $clean) ?: [];
        $masked = array_map(static function (string $word): string {
            $first = mb_substr($word, 0, 1);

            return $first . str_repeat('*', max(2, min(4, mb_strlen($word) - 1)));
        }, array_slice($parts, 0, 3));

        return implode(' ', $masked);
    }

    private function maskEmail(?string $email): string
    {
        $clean = trim((string) $email);
        if (! str_contains($clean, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $clean, 2);
        $prefix = mb_substr($local, 0, min(2, mb_strlen($local)));

        return "{$prefix}***@{$domain}";
    }

    private function maskPhone(?string $phone): string
    {
        $clean = trim((string) $phone);
        if ($clean === '') {
            return 'Protegido';
        }

        $digits = preg_replace('/\D+/', '', $clean) ?? '';
        if (strlen($digits) < 3) {
            return '***';
        }

        return '••• ••• ' . substr($digits, -3);
    }

    private function maskDocument(?string $docType, ?string $docNumber): string
    {
        $type = trim((string) $docType) ?: 'DOC';
        $num = trim((string) $docNumber);
        if ($num === '') {
            return "{$type} verificado";
        }

        $visible = substr($num, -4);

        return "{$type} ••••{$visible}";
    }

    private function maskReference(?string $reference): ?string
    {
        $clean = trim((string) $reference);
        if ($clean === '') {
            return null;
        }

        if (strlen($clean) <= 4) {
            return str_repeat('•', strlen($clean));
        }

        return '••••' . substr($clean, -4);
    }

    private function maxReturnableQuantity(OrderItem $orderItem): int
    {
        $alreadyRequested = (int) $orderItem->returnItems
            ->filter(fn (ReturnItem $ri): bool => ! in_array($ri->orderReturn?->status, ['rechazado', 'cancelado'], true))
            ->sum('quantity');

        return max(0, (int) $orderItem->quantity - $alreadyRequested);
    }

    private function generateUniqueRmaNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "RMA-{$year}-";
        $latest = OrderReturn::query()
            ->where('rma_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('rma_number');

        $nextNumber = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) === 1) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $exists = OrderReturn::query()->where('rma_number', $candidate)->exists();
            $nextNumber++;
        } while ($exists);

        return $candidate;
    }
}
