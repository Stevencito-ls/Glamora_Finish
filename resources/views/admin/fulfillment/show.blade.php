@extends('layouts.admin')

@section('title', 'Pedido ' . $order->order_number . ' — Fulfillment Glamora')
@section('page_title', 'Pedido ' . $order->order_number)
@section('page_subtitle', 'Tracking ' . ($order->tracking_code ?? '—') . ' · Canal ' . ($order->is_b2b ? 'Mayorista B2B' : 'Boutique') . ' · ' . $order->created_at?->format('d/m/Y H:i'))

@section('header_actions')
    <a href="{{ route('admin.fulfillment.orders.dispatch-guide-pdf', $order) }}" class="rounded-full border border-glamora-gold/60 bg-glamora-gold/15 px-4 py-2 text-xs font-semibold text-glamora-charcoal transition hover:bg-glamora-gold/30">
        Descargar Guía Picking PDF
    </a>
    <a href="{{ route('admin.fulfillment.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Volver al Tablero
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- Order Overview & Status Actions --}}
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card-glamora p-6 lg:col-span-2 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-glamora-blush/30 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $order->status->badgeClasses() }}">
                            {{ $order->status->label() }}
                        </span>
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $order->customer_tier->badgeClasses() }}">
                            {{ $order->customer_tier->label() }}
                        </span>
                    </div>
                    <span class="font-serif text-2xl font-bold text-glamora-charcoal">
                        S/ {{ number_format((float) $order->total, 2) }}
                    </span>
                </div>

                <div class="grid gap-4 text-xs sm:grid-cols-2">
                    <div class="rounded-xl bg-glamora-cream/60 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">Cliente &amp; Facturación</p>
                        <p class="mt-1.5 font-semibold text-glamora-charcoal">{{ $order->customer_name }}</p>
                        <p class="text-glamora-muted">{{ $order->document_type }}: {{ $order->document_number ?? '—' }}</p>
                        <p class="text-glamora-muted">{{ $order->customer_email }} · {{ $order->customer_phone ?? '—' }}</p>
                    </div>

                    <div class="rounded-xl bg-glamora-cream/60 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">Destino &amp; Método de Envío</p>
                        <p class="mt-1.5 font-semibold text-glamora-charcoal">{{ $order->shipping_address }}</p>
                        <p class="text-glamora-muted">{{ $order->shipping_district }}, {{ $order->shipping_city }}</p>
                        <p class="text-glamora-muted">
                            {{ $order->shipping_method->label() }}
                            (Envío: {{ (float) $order->shipping_cost === 0.0 ? 'GRATIS' : 'S/ ' . number_format((float) $order->shipping_cost, 2) }})
                        </p>
                    </div>
                </div>

                @if ($order->notes)
                    <div class="rounded-xl border border-glamora-blush/35 bg-white p-3.5 text-xs text-glamora-charcoal">
                        <p class="font-bold uppercase tracking-wider text-[10px] text-glamora-muted">Bitácora / Notas del Pedido</p>
                        <p class="mt-1 whitespace-pre-line">{{ $order->notes }}</p>
                    </div>
                @endif
            </section>

            {{-- Fulfillment Transitions --}}
            <section class="card-glamora p-6 space-y-4">
                <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                    Flujo Fulfillment &amp; FEFO
                </h2>

                <div class="space-y-1.5 text-xs text-glamora-muted">
                    <p>Despacho FEFO: <strong class="text-glamora-charcoal">{{ $order->fefo_dispatched_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</strong></p>
                    <p>Entregado: <strong class="text-glamora-charcoal">{{ $order->delivered_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</strong></p>
                </div>

                @if ($nextStatuses->isEmpty())
                    <div class="rounded-xl bg-glamora-cream p-3.5 text-xs text-glamora-muted">
                        Este pedido se encuentra en un estado terminal ({{ $order->status->label() }}).
                    </div>
                @else
                    @can('orders.update_status')
                        @php
                            $hasVerifiedPayment = $order->payments->contains(fn ($p): bool => $p->status === 'verified');
                            $allowedNextStatuses = $nextStatuses->filter(function (\App\Enums\OrderStatus $next) use ($hasVerifiedPayment): bool {
                                return match ($next) {
                                    \App\Enums\OrderStatus::Despachado => auth()->user()->can('orders.dispatch_fefo'),
                                    \App\Enums\OrderStatus::Cancelado => auth()->user()->can('orders.cancel'),
                                    \App\Enums\OrderStatus::PagoVerificado => $hasVerifiedPayment || auth()->user()->can('payments.verify'),
                                    default => true,
                                };
                            })->values();
                        @endphp
                        @if ($allowedNextStatuses->isEmpty())
                            <div class="rounded-xl bg-glamora-cream p-3.5 text-xs text-glamora-muted">
                                Esperando verificación financiera de pago o permisos adicionales para continuar.
                            </div>
                        @else
                            <form action="{{ route('admin.fulfillment.orders.transition', $order) }}" method="POST" class="space-y-3 border-t border-glamora-blush/30 pt-3">
                                @csrf
                                @method('PATCH')

                                <div>
                                    <label for="next_status" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">
                                        Siguiente Estado *
                                    </label>
                                    <select id="next_status" name="status" required class="input-glamora text-xs">
                                        @foreach ($allowedNextStatuses as $next)
                                            <option value="{{ $next->value }}">{{ $next->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="transition_notes" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">
                                        Observación operativa (obligatoria si cancelas)
                                    </label>
                                    <textarea id="transition_notes" name="notes" rows="2" placeholder="Ej. Picking validado, guía courier asignada..." class="input-glamora text-xs">{{ old('notes') }}</textarea>
                                </div>

                                <button type="submit" class="btn-glamora-primary w-full py-2.5 text-xs">
                                    Ejecutar Transición de Estado
                                </button>
                            </form>
                        @endif
                    @endcan
                @endif
            </section>
        </div>

        {{-- Items & FEFO Allocations Table --}}
        <section class="card-glamora overflow-hidden">
            <div class="border-b border-glamora-blush/30 bg-white px-6 py-4">
                <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                    Ítems del Pedido, Lotes FEFO &amp; Ubicaciones en Almacén
                </h2>
            </div>

            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">SKU</th>
                            <th class="px-5 py-3 text-left">Producto &amp; Tono</th>
                            <th class="px-5 py-3 text-left">Trazabilidad FEFO &amp; Ubicación</th>
                            <th class="px-5 py-3 text-center">Cant.</th>
                            <th class="px-5 py-3 text-right">Unitario</th>
                            <th class="px-5 py-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @foreach ($order->items as $item)
                            @php
                                $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($item->shade_hex ?? '')) === 1
                                    ? (string) $item->shade_hex
                                    : '#C87D72';
                                $allocations = is_array($item->batch_allocations) ? $item->batch_allocations : [];
                            @endphp
                            <tr>
                                <td class="px-5 py-3.5 font-mono font-bold text-glamora-charcoal">{{ $item->sku }}</td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-glamora-charcoal">{{ $item->product_name }}</p>
                                    <p class="flex items-center gap-1.5 text-[11px] text-glamora-muted">
                                        <span class="inline-block h-3 w-3 rounded-full border border-black/20" style="background-color: {{ $safeHex }};"></span>
                                        {{ $item->variant_name }}
                                    </p>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if (count($allocations) > 0)
                                        <div class="space-y-1">
                                            @foreach ($allocations as $alloc)
                                                @php
                                                    $bModel = isset($alloc['batch_id']) ? $batchLookup->get((int) $alloc['batch_id']) : null;
                                                    $loc = $bModel?->warehouseLocation;
                                                @endphp
                                                <div class="rounded-lg border border-glamora-gold/40 bg-glamora-cream/70 px-2.5 py-1 text-[11px]">
                                                    <strong class="font-mono">{{ $alloc['batch_number'] ?? $bModel?->batch_number ?? 'FEFO' }}</strong>
                                                    &times;{{ $alloc['quantity'] ?? $item->quantity }}
                                                    (vence {{ $alloc['expiration_date'] ?? $bModel?->expiration_date?->format('Y-m-d') ?? '—' }})
                                                    @if ($loc)
                                                        · <span class="font-mono font-semibold text-glamora-terracotta">{{ $loc->code }}</span>
                                                        ({{ $loc->aisle }}-{{ $loc->rack }}-{{ $loc->shelf }})
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif ($item->batch)
                                        <span class="font-mono text-[11px]">
                                            {{ $item->batch->batch_number }} ({{ $item->batch->warehouseLocation?->code ?? '—' }})
                                        </span>
                                    @else
                                        <span class="text-amber-700">Pendiente de asignación FEFO</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-center font-bold">{{ $item->quantity }}</td>
                                <td class="px-5 py-3.5 text-right">S/ {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-5 py-3.5 text-right font-bold">S/ {{ number_format((float) $item->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Payments & Voucher Verification --}}
        @can('payments.view')
            <section class="card-glamora overflow-hidden">
                <div class="border-b border-glamora-blush/30 bg-white px-6 py-4">
                    <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                        Conciliación de Pagos &amp; Comprobantes
                    </h2>
                </div>

                <div class="divide-y divide-glamora-blush/25 bg-white">
                    @forelse ($order->payments as $payment)
                        <div class="space-y-4 p-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="space-y-1 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm font-bold text-glamora-charcoal">{{ $payment->payment_method->label() }}</span>
                                        <span @class([
                                            'rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase',
                                            'border-emerald-200 bg-emerald-50 text-emerald-700' => $payment->status === 'verified',
                                            'border-amber-200 bg-amber-50 text-amber-800' => $payment->status === 'pending',
                                            'border-rose-200 bg-rose-50 text-rose-700' => in_array($payment->status, ['rejected', 'refunded'], true),
                                        ])>
                                            {{ $payment->status }}
                                        </span>
                                    </div>
                                    <p class="text-glamora-muted">
                                        Monto: <strong class="text-glamora-charcoal">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</strong>
                                        @if ($payment->transaction_reference)
                                            · Ref.: <span class="font-mono text-glamora-charcoal">{{ $payment->transaction_reference }}</span>
                                        @endif
                                    </p>
                                    @if ($payment->crypto_network || $payment->crypto_tx_hash)
                                        <p class="font-mono text-[11px] text-glamora-muted">
                                            Blockchain {{ $payment->crypto_network }} · Tx: {{ $payment->crypto_tx_hash }}
                                        </p>
                                    @endif
                                    @if ($payment->b2b_due_date)
                                        <p class="text-glamora-muted">
                                            Vencimiento Crédito B2B: <strong class="text-glamora-charcoal">{{ $payment->b2b_due_date->format('d/m/Y') }}</strong>
                                        </p>
                                    @endif
                                    @if ($payment->verified_at)
                                        <p class="text-[11px] text-emerald-700">
                                            Procesado el {{ $payment->verified_at->format('d/m/Y H:i') }}
                                            @if ($payment->verifier)
                                                por {{ $payment->verifier->name }}
                                            @endif
                                        </p>
                                    @endif
                                    @if ($payment->notes)
                                        <p class="text-[11px] text-glamora-muted">{{ $payment->notes }}</p>
                                    @endif
                                </div>

                                @if ($payment->voucher_path)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a
                                            href="{{ route('admin.fulfillment.payments.voucher', $payment) }}"
                                            target="_blank"
                                            class="rounded-full border border-glamora-charcoal/20 bg-glamora-cream px-3.5 py-1.5 text-xs font-semibold text-glamora-charcoal hover:bg-glamora-charcoal hover:text-glamora-cream"
                                        >
                                            Ver Voucher
                                        </a>
                                        <a
                                            href="{{ route('admin.fulfillment.payments.voucher', ['payment' => $payment, 'download' => 1]) }}"
                                            class="rounded-full border border-glamora-terracotta/40 px-3.5 py-1.5 text-xs font-semibold text-glamora-terracotta hover:bg-glamora-terracotta hover:text-white"
                                        >
                                            Descargar
                                        </a>
                                    </div>
                                @endif
                            </div>

                            @if ($payment->voucher_path && ! str_ends_with(strtolower($payment->voucher_path), '.pdf'))
                                <div class="max-w-xs overflow-hidden rounded-xl border border-glamora-blush/40 bg-glamora-cream p-2">
                                    <img
                                        src="{{ route('admin.fulfillment.payments.voucher', $payment) }}"
                                        alt="Comprobante de pago {{ $order->order_number }}"
                                        class="max-h-48 w-full rounded-lg object-contain"
                                    >
                                </div>
                            @endif

                            @if ($payment->status === 'pending')
                                @can('payments.verify')
                                    <div class="grid gap-4 border-t border-glamora-blush/25 pt-4 md:grid-cols-2">
                                        <form action="{{ route('admin.fulfillment.payments.verify', $payment) }}" method="POST" class="flex flex-col gap-2 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                                            @csrf
                                            <label class="text-xs font-bold text-emerald-900">Aprobar y Conciliar Pago</label>
                                            <input type="text" name="verification_notes" placeholder="Nro. extracto bancario o nota opcional..." class="input-glamora text-xs">
                                            <button type="submit" class="rounded-full bg-emerald-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-800">
                                                Verificar Pago &amp; Avanzar a Pago Verificado
                                            </button>
                                        </form>

                                        <form action="{{ route('admin.fulfillment.payments.reject', $payment) }}" method="POST" class="flex flex-col gap-2 rounded-xl border border-rose-200 bg-rose-50/50 p-4">
                                            @csrf
                                            <label class="text-xs font-bold text-rose-900">Rechazar Comprobante</label>
                                            <input type="text" name="rejection_reason" required placeholder="Motivo del rechazo (ej. voucher ilegible / monto incompleto)..." class="input-glamora text-xs">
                                            @can('orders.cancel')
                                                <label class="inline-flex items-center gap-2 text-[11px] text-rose-900">
                                                    <input type="checkbox" name="cancel_order" value="1" class="accent-rose-700">
                                                    Cancelar pedido y liberar reserva FEFO inmediatamente
                                                </label>
                                            @endcan
                                            <button type="submit" class="rounded-full bg-rose-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-rose-800">
                                                Rechazar Pago
                                            </button>
                                        </form>
                                    </div>
                                @endcan
                            @endif
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-glamora-muted">
                            No hay pagos registrados para este pedido.
                        </div>
                    @endforelse
                </div>
            </section>
        @endcan
    </div>
@endsection
