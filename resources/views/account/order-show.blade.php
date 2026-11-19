@extends('layouts.storefront')

@section('title', "Pedido {$order->order_number} — Mi Cuenta Glamora")
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @component('account.partials.shell')
        @php
            $primaryPayment = $order->payments->first();
            $netSubtotal = max(0.0, (float) $order->subtotal - (float) $order->discount_amount);
            $taxableBase = round($netSubtotal - (float) $order->tax_igv, 2);
        @endphp

        <div class="card-glamora overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-glamora-blush/30 bg-glamora-charcoal px-6 py-5 text-glamora-cream">
                <div>
                    <a href="{{ route('account.orders') }}" class="text-xs font-semibold text-glamora-gold hover:underline">
                        &larr; Volver a Mis Pedidos
                    </a>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h2 class="font-mono text-2xl font-bold text-white">{{ $order->order_number }}</h2>
                        <span class="inline-flex rounded-full border px-3 py-0.5 text-xs font-bold {{ $order->status->badgeClasses() }}">
                            {{ $order->status->label() }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-glamora-cream/75">
                        Registrado el {{ $order->created_at?->format('d/m/Y H:i') }} · Tracking: <span class="font-mono">{{ $order->tracking_code }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2.5">
                    <a href="{{ route('tracking.index', ['codigo' => $order->order_number]) }}" class="rounded-full border border-white/25 px-4 py-2 text-xs font-semibold text-white transition hover:bg-white/10">
                        Ver Timeline
                    </a>
                    <a href="{{ route('account.orders.pdf', $order->order_number) }}" class="rounded-full bg-glamora-gold px-4 py-2 text-xs font-bold text-glamora-charcoal transition hover:bg-white">
                        Descargar PDF
                    </a>
                </div>
            </div>

            <div class="space-y-6 p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-glamora-blush/35 bg-glamora-cream/60 p-4 text-sm">
                        <h3 class="font-serif text-lg font-semibold text-glamora-charcoal">Envío &amp; Destinatario</h3>
                        <p class="mt-2 text-glamora-charcoal">{{ $order->customer_name }} ({{ $order->document_type }} {{ $order->document_number }})</p>
                        <p class="text-xs text-glamora-muted">{{ $order->shipping_address }}, {{ $order->shipping_district }}, {{ $order->shipping_city }}</p>
                        <p class="mt-1 text-xs font-semibold text-glamora-terracotta">{{ $order->shipping_method->label() }}</p>
                    </div>

                    <div class="rounded-2xl border border-glamora-blush/35 bg-glamora-cream/60 p-4 text-sm">
                        <h3 class="font-serif text-lg font-semibold text-glamora-charcoal">Pago</h3>
                        @if ($primaryPayment)
                            <p class="mt-2 text-glamora-charcoal">{{ $primaryPayment->payment_method->label() }}</p>
                            <p class="text-xs text-glamora-muted">
                                Estado: <strong>{{ $primaryPayment->status === 'verified' ? 'Verificado' : 'Pendiente' }}</strong>
                                @if ($primaryPayment->transaction_reference)
                                    · Ref: <span class="font-mono">{{ $primaryPayment->transaction_reference }}</span>
                                @endif
                            </p>
                            @if ($primaryPayment->b2b_due_date)
                                <p class="mt-1 text-xs text-glamora-terracotta">
                                    Vencimiento crédito B2B: {{ $primaryPayment->b2b_due_date->format('d/m/Y') }}
                                </p>
                            @endif
                            @if ($primaryPayment->voucher_path)
                                <a
                                    href="{{ asset('storage/' . ltrim($primaryPayment->voucher_path, '/')) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-2 inline-block text-xs font-semibold text-glamora-terracotta hover:underline"
                                >
                                    Ver comprobante adjunto &rarr;
                                </a>
                            @endif
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="mb-3 font-serif text-xl font-semibold text-glamora-charcoal">Productos &amp; Lotes FEFO</h3>
                    <div class="divide-y divide-glamora-blush/25 rounded-2xl border border-glamora-blush/35 bg-white">
                        @foreach ($order->items as $item)
                            @php
                                $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($item->shade_hex ?? '')) === 1
                                    ? (string) $item->shade_hex
                                    : '#C87D72';
                                $allocations = is_array($item->batch_allocations) ? $item->batch_allocations : [];
                            @endphp
                            <div class="flex flex-wrap items-center justify-between gap-4 p-4">
                                <div>
                                    <p class="font-semibold text-glamora-charcoal">{{ $item->product_name }}</p>
                                    <p class="mt-0.5 flex items-center gap-2 text-xs text-glamora-muted">
                                        <span class="inline-block h-3 w-3 rounded-full border border-black/15" style="background-color: {{ $safeHex }}"></span>
                                        <span>{{ $item->variant_name }}</span>
                                        <span class="font-mono">· {{ $item->sku }}</span>
                                        <span>· {{ $item->quantity }} &times; S/ {{ number_format((float) $item->unit_price, 2) }}</span>
                                    </p>
                                    @if (count($allocations) > 0)
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($allocations as $alloc)
                                                <span class="rounded-full border border-glamora-gold/40 bg-glamora-cream px-2.5 py-0.5 font-mono text-[10px] text-glamora-charcoal">
                                                    Lote {{ $alloc['batch_number'] ?? 'FEFO' }} · x{{ $alloc['quantity'] ?? $item->quantity }} (vence {{ $alloc['expiration_date'] ?? '—' }})
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <span class="font-serif text-lg font-bold text-glamora-charcoal">
                                    S/ {{ number_format((float) $item->subtotal, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="ml-auto max-w-sm rounded-2xl border border-glamora-blush/35 bg-glamora-cream/70 p-4 text-sm">
                    <dl class="space-y-1.5">
                        <div class="flex justify-between">
                            <dt class="text-glamora-muted">Subtotal</dt>
                            <dd>S/ {{ number_format((float) $order->subtotal, 2) }}</dd>
                        </div>
                        @if ((float) $order->discount_amount > 0)
                            <div class="flex justify-between text-emerald-700">
                                <dt>Descuento ({{ $order->coupon_code }})</dt>
                                <dd>-S/ {{ number_format((float) $order->discount_amount, 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between text-xs text-glamora-muted">
                            <dt>Base Imponible</dt>
                            <dd>S/ {{ number_format($taxableBase, 2) }}</dd>
                        </div>
                        <div class="flex justify-between text-xs text-glamora-muted">
                            <dt>IGV (18% incluido)</dt>
                            <dd>S/ {{ number_format((float) $order->tax_igv, 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-glamora-muted">Envío</dt>
                            <dd>{{ (float) $order->shipping_cost === 0.0 ? 'GRATIS' : 'S/ ' . number_format((float) $order->shipping_cost, 2) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-glamora-blush/40 pt-2 font-serif text-xl font-bold text-glamora-charcoal">
                            <dt>Total</dt>
                            <dd>S/ {{ number_format((float) $order->total, 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    @endcomponent
@endsection
