<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\SecurityActionTriggered;
use App\Exceptions\InsufficientFefoStockException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WholesaleProfile;
use App\Services\FefoService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FulfillmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        /** @var User $user */
        $user = Auth::user();
        $isOpsStaff = $user->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero']);

        $statusFilter = (string) $request->query('status', '');
        $paymentStatus = (string) $request->query('payment_status', '');
        $b2bFilter = (string) $request->query('b2b', '');
        $search = trim((string) $request->query('q', ''));

        $baseQuery = Order::query();
        if (! $isOpsStaff) {
            $baseQuery->where('user_id', $user->id);
        }

        $statusCounts = (clone $baseQuery)
            ->reorder()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as status_count')
            ->pluck('status_count', 'status');

        $orders = (clone $baseQuery)
            ->with(['payments', 'items', 'user'])
            ->when($statusFilter !== '' && in_array($statusFilter, OrderStatus::values(), true), function ($q) use ($statusFilter): void {
                $q->where('status', $statusFilter);
            })
            ->when($paymentStatus !== '', function ($q) use ($paymentStatus): void {
                $q->whereHas('payments', fn ($pq) => $pq->where('status', $paymentStatus));
            })
            ->when($b2bFilter === '1', fn ($q) => $q->where('is_b2b', true))
            ->when($b2bFilter === '0', fn ($q) => $q->where('is_b2b', false))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('order_number', 'like', "%{$search}%")
                        ->orWhere('tracking_code', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(18)
            ->withQueryString();

        return view('admin.fulfillment.index', [
            'orders' => $orders,
            'orderStatuses' => OrderStatus::cases(),
            'statusCounts' => $statusCounts,
            'statusFilter' => $statusFilter,
            'paymentStatus' => $paymentStatus,
            'b2bFilter' => $b2bFilter,
            'search' => $search,
            'isOpsStaff' => $isOpsStaff,
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load([
            'items.variant.product',
            'items.batch.warehouseLocation',
            'payments.verifier',
            'returns.items',
            'user.wholesaleProfile',
        ]);

        $batchLookup = $this->resolveBatchLookupForOrder($order);

        $nextStatuses = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $candidate): bool => $order->status->canTransitionTo($candidate))
            ->values();

        return view('admin.fulfillment.show', [
            'order' => $order,
            'batchLookup' => $batchLookup,
            'nextStatuses' => $nextStatuses,
        ]);
    }

    public function voucher(Request $request, Payment $payment): StreamedResponse|Response
    {
        $order = $payment->order()->firstOrFail();
        $this->authorize('viewPayment', $order);

        $rawPath = trim((string) $payment->voucher_path);
        if (
            $rawPath === ''
            || str_contains($rawPath, "\0")
            || str_contains($rawPath, '..')
            || str_contains($rawPath, ':')
        ) {
            abort(404, 'Comprobante no disponible.');
        }

        $normalized = ltrim(str_replace('\\', '/', $rawPath), '/');
        $normalized = (string) preg_replace('#^storage/(?=vouchers/)#', '', $normalized);

        if (! str_starts_with($normalized, 'vouchers/')) {
            abort(404, 'Comprobante no disponible.');
        }

        $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

        if (! in_array($extension, $allowedExtensions, true)) {
            abort(404, 'Formato de comprobante no permitido.');
        }

        foreach (['public', 'local'] as $diskName) {
            $disk = Storage::disk($diskName);
            if (! $disk->exists($normalized)) {
                continue;
            }

            $baseRealPath = realpath($disk->path(''));
            $fileRealPath = realpath($disk->path($normalized));
            if (
                $baseRealPath === false
                || $fileRealPath === false
                || ! str_starts_with($fileRealPath, rtrim($baseRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            ) {
                abort(404, 'Comprobante no disponible.');
            }

            $mime = strtolower((string) $disk->mimeType($normalized));
            if (! in_array($mime, $allowedMimes, true)) {
                abort(404, 'Tipo MIME de comprobante no permitido.');
            }

            $headers = ['X-Content-Type-Options' => 'nosniff'];

            return $request->boolean('download')
                ? $disk->download($normalized, null, $headers)
                : $disk->response($normalized, null, $headers);
        }

        abort(404, 'Archivo de comprobante no encontrado en el almacenamiento.');
    }

    public function verifyPayment(Request $request, Payment $payment, FefoService $fefoService): RedirectResponse
    {
        $order = $payment->order()->firstOrFail();
        $this->authorize('verifyPayment', $order);

        $validated = $request->validate([
            'verification_notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            DB::transaction(function () use ($payment, $order, $validated, $actor, $fefoService): void {
                /** @var Payment $lockedPayment */
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()->with(['items.variant', 'payments'])->lockForUpdate()->findOrFail($order->id);

                if ($lockedPayment->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'payment' => "El pago #{$lockedPayment->id} ya fue procesado (estado actual: {$lockedPayment->status}).",
                    ]);
                }

                if ($lockedOrder->status === OrderStatus::Cancelado) {
                    throw ValidationException::withMessages([
                        'payment' => "No se puede verificar un pago de un pedido cancelado ({$lockedOrder->order_number}).",
                    ]);
                }

                $extraNote = isset($validated['verification_notes']) && trim((string) $validated['verification_notes']) !== ''
                    ? ' | Verificación: ' . trim((string) $validated['verification_notes'])
                    : '';

                $lockedPayment->update([
                    'status' => 'verified',
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                    'notes' => trim((string) ($lockedPayment->notes ?? '')) . $extraNote,
                ]);

                // Ensure FEFO reservation exists if order had no batch allocation yet (e.g., seeded PendientePago)
                if ($lockedOrder->status === OrderStatus::PendientePago) {
                    $this->ensureFefoReservations($lockedOrder, $fefoService, $actor);

                    $verifiedTotal = (float) Payment::query()
                        ->where('order_id', $lockedOrder->id)
                        ->where('status', 'verified')
                        ->sum('amount');

                    if ($verifiedTotal + 0.01 >= (float) $lockedOrder->total) {
                        $lockedOrder->status = OrderStatus::PagoVerificado;
                        $lockedOrder->save();
                    }
                }

                SecurityActionTriggered::dispatch(
                    $actor,
                    'payment_verified',
                    'info',
                    "Pago #{$lockedPayment->id} ({$lockedPayment->payment_method->label()}) verificado para {$lockedOrder->order_number}.",
                    [
                        'order_id' => $lockedOrder->id,
                        'order_number' => $lockedOrder->order_number,
                        'payment_id' => $lockedPayment->id,
                        'amount' => (float) $lockedPayment->amount,
                        'order_status' => $lockedOrder->status->value,
                    ],
                );
            });
        } catch (InsufficientFefoStockException $e) {
            return back()->with('admin_error', $e->getMessage());
        }

        return redirect()
            ->route('admin.fulfillment.orders.show', $order)
            ->with('admin_success', "Pago #{$payment->id} verificado correctamente. Pedido {$order->order_number} listo para Fulfillment FEFO.");
    }

    public function rejectPayment(Request $request, Payment $payment, FefoService $fefoService): RedirectResponse
    {
        $order = $payment->order()->firstOrFail();
        $this->authorize('verifyPayment', $order);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
            'cancel_order' => ['nullable', 'boolean'],
        ], [
            'rejection_reason.required' => 'Indica el motivo del rechazo del comprobante o pago.',
            'rejection_reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $cancelOrder = $request->boolean('cancel_order');

        if ($cancelOrder && ! $actor->can('orders.cancel')) {
            abort(403, 'No tienes permiso para cancelar pedidos al rechazar el pago.');
        }

        DB::transaction(function () use ($payment, $order, $validated, $actor, $cancelOrder, $fefoService): void {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->with(['payments'])->lockForUpdate()->findOrFail($order->id);

            if ($lockedPayment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => "El pago #{$lockedPayment->id} ya no está pendiente (estado: {$lockedPayment->status}).",
                ]);
            }

            $reason = trim($validated['rejection_reason']);
            $lockedPayment->update([
                'status' => 'rejected',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'notes' => trim((string) ($lockedPayment->notes ?? '')) . " | Rechazado: {$reason}",
            ]);

            if ($cancelOrder && $lockedOrder->status->canTransitionTo(OrderStatus::Cancelado)) {
                $fefoService->releaseForCancellation($lockedOrder, $actor);
                $this->revertB2bCreditIfApplicable($lockedOrder);
                $lockedOrder->status = OrderStatus::Cancelado;
                $lockedOrder->notes = trim(($lockedOrder->notes ? $lockedOrder->notes . "\n" : '') . "[Cancelación por pago rechazado]: {$reason}");
                $lockedOrder->save();
            }

            SecurityActionTriggered::dispatch(
                $actor,
                'payment_rejected',
                'warning',
                "Pago #{$lockedPayment->id} rechazado en pedido {$lockedOrder->order_number}: {$reason}",
                [
                    'order_id' => $lockedOrder->id,
                    'order_number' => $lockedOrder->order_number,
                    'payment_id' => $lockedPayment->id,
                    'order_cancelled' => $cancelOrder,
                ],
            );
        });

        return redirect()
            ->route('admin.fulfillment.orders.show', $order)
            ->with('admin_success', "Pago #{$payment->id} marcado como rechazado.");
    }

    public function transitionStatus(Request $request, Order $order, FefoService $fefoService): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(OrderStatus::values())],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $targetStatus = OrderStatus::from($validated['status']);
        /** @var User $actor */
        $actor = Auth::user();

        if ($targetStatus === OrderStatus::Despachado) {
            $this->authorize('dispatchFefo', $order);
        } elseif ($targetStatus === OrderStatus::Cancelado) {
            $this->authorize('cancel', $order);
        } else {
            $this->authorize('updateStatus', $order);
        }

        if ($targetStatus === OrderStatus::Cancelado && trim((string) ($validated['notes'] ?? '')) === '') {
            return back()->withErrors(['notes' => 'Indica el motivo de cancelación del pedido.']);
        }

        try {
            DB::transaction(function () use ($order, $targetStatus, $validated, $actor, $fefoService): void {
                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()
                    ->with(['items.variant', 'payments', 'user'])
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                $previousStatus = $lockedOrder->status;

                if (! $previousStatus->canTransitionTo($targetStatus)) {
                    throw ValidationException::withMessages([
                        'status' => "Transición inválida desde «{$previousStatus->label()}» hacia «{$targetStatus->label()}».",
                    ]);
                }

                if ($targetStatus === OrderStatus::PagoVerificado) {
                    $hasVerifiedPayment = $lockedOrder->payments->contains(
                        fn (Payment $p): bool => $p->status === 'verified'
                    );

                    if (! $hasVerifiedPayment && ! $actor->can('payments.verify')) {
                        throw ValidationException::withMessages([
                            'status' => 'El pedido requiere verificación de pago antes de avanzar a Pago Verificado.',
                        ]);
                    }

                    $this->ensureFefoReservations($lockedOrder, $fefoService, $actor);
                }

                if ($targetStatus === OrderStatus::Despachado) {
                    $fefoService->dispatchReservedOrder($lockedOrder, $actor);
                    $lockedOrder->fefo_dispatched_at = now();
                }

                if ($targetStatus === OrderStatus::Entregado) {
                    $lockedOrder->delivered_at = now();
                }

                if ($targetStatus === OrderStatus::Cancelado) {
                    $fefoService->releaseForCancellation($lockedOrder, $actor);
                    $this->revertB2bCreditIfApplicable($lockedOrder);
                }

                if (! empty($validated['notes'])) {
                    $noteLine = '[' . now()->format('d/m H:i') . " {$targetStatus->label()}]: " . trim((string) $validated['notes']);
                    $lockedOrder->notes = trim(($lockedOrder->notes ? $lockedOrder->notes . "\n" : '') . $noteLine);
                }

                $lockedOrder->status = $targetStatus;
                $lockedOrder->save();

                $eventType = match ($targetStatus) {
                    OrderStatus::Despachado => 'order_dispatched_fefo',
                    OrderStatus::Cancelado => 'order_cancelled',
                    default => 'order_status_updated',
                };

                SecurityActionTriggered::dispatch(
                    $actor,
                    $eventType,
                    $targetStatus === OrderStatus::Cancelado ? 'warning' : 'info',
                    "Pedido {$lockedOrder->order_number} cambió de «{$previousStatus->label()}» a «{$targetStatus->label()}».",
                    [
                        'order_id' => $lockedOrder->id,
                        'order_number' => $lockedOrder->order_number,
                        'from_status' => $previousStatus->value,
                        'to_status' => $targetStatus->value,
                    ],
                );
            });
        } catch (InsufficientFefoStockException $e) {
            return back()->with('admin_error', $e->getMessage());
        }

        return redirect()
            ->route('admin.fulfillment.orders.show', $order)
            ->with('admin_success', "Estado del pedido {$order->order_number} actualizado a «{$targetStatus->label()}».");
    }

    public function dispatchGuidePdf(Order $order, SettingService $settingService): Response
    {
        $this->authorize('downloadDispatchGuide', $order);

        $order->load([
            'items.variant.product',
            'items.batch.warehouseLocation',
            'payments',
            'user',
        ]);

        $batchLookup = $this->resolveBatchLookupForOrder($order);

        $pdf = Pdf::loadView('pdf.dispatch-guide', [
            'order' => $order,
            'batchLookup' => $batchLookup,
            'settings' => $settingService->all(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Guia-Picking-{$order->order_number}.pdf");
    }

    private function ensureFefoReservations(Order $lockedOrder, FefoService $fefoService, User $actor): void
    {
        foreach ($lockedOrder->items as $item) {
            $hasAllocations = is_array($item->batch_allocations) && $item->batch_allocations !== [];
            if (! $hasAllocations && $item->product_batch_id === null && $item->variant instanceof ProductVariant) {
                $allocations = $fefoService->allocateForOrder(
                    variant: $item->variant,
                    quantityNeeded: (int) $item->quantity,
                    order: $lockedOrder,
                    mode: 'reserve',
                    actor: $actor,
                );

                $item->update([
                    'product_batch_id' => $allocations[0]['batch_id'] ?? null,
                    'batch_allocations' => $allocations,
                ]);
            }
        }
    }

    private function revertB2bCreditIfApplicable(Order $lockedOrder): void
    {
        if ($lockedOrder->user_id === null) {
            return;
        }

        $b2bPayments = $lockedOrder->payments->filter(
            fn (Payment $p): bool => $p->payment_method === PaymentMethod::CreditoB2b && $p->status !== 'refunded'
        );

        if ($b2bPayments->isEmpty()) {
            return;
        }

        $profile = WholesaleProfile::query()
            ->where('user_id', $lockedOrder->user_id)
            ->lockForUpdate()
            ->first();

        $totalCreditToRevert = (float) $b2bPayments->sum('amount');

        if ($profile instanceof WholesaleProfile && $totalCreditToRevert > 0.0) {
            $profile->credit_used = max(0.0, round((float) $profile->credit_used - $totalCreditToRevert, 2));
            $profile->save();
        }

        foreach ($b2bPayments as $b2bPayment) {
            $b2bPayment->update([
                'status' => 'refunded',
                'notes' => trim((string) ($b2bPayment->notes ?? '')) . ' | Crédito B2B revertido por cancelación.',
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProductBatch>
     */
    private function resolveBatchLookupForOrder(Order $order): \Illuminate\Support\Collection
    {
        $batchIds = [];

        foreach ($order->items as $item) {
            if ($item->product_batch_id !== null) {
                $batchIds[] = (int) $item->product_batch_id;
            }
            if (is_array($item->batch_allocations)) {
                foreach ($item->batch_allocations as $alloc) {
                    if (is_array($alloc) && isset($alloc['batch_id'])) {
                        $batchIds[] = (int) $alloc['batch_id'];
                    }
                }
            }
        }

        $uniqueIds = array_values(array_unique(array_filter($batchIds)));
        if ($uniqueIds === []) {
            return collect();
        }

        return ProductBatch::query()
            ->with('warehouseLocation')
            ->whereIn('id', $uniqueIds)
            ->get()
            ->keyBy('id');
    }
}
