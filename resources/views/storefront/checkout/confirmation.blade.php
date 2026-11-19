@extends('layouts.storefront')

@section('title', "Pedido Confirmado {$order->order_number} — Glamora Perú")
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @php
        $primaryPayment = $order->payments->first();
    @endphp

    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="card-glamora overflow-hidden">
            {{-- Celebration Banner --}}
            <div class="border-b border-glamora-blush/35 bg-glamora-charcoal px-6 py-8 text-glamora-cream sm:px-10">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-glamora-gold/50 bg-glamora-gold/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-glamora-gold">
                            Pedido Registrado con Trazabilidad FEFO
                        </span>
                        <h1 class="mt-3 font-serif text-3xl font-semibold text-white sm:text-4xl">
                            ¡Gracias, {{ $order->customer_name }}!
                        </h1>
                        <p class="mt-1.5 text-sm text-glamora-cream/80">
                            Tu orden ha sido registrada con código <strong class="font-mono text-glamora-gold">{{ $order->order_number }}</strong> y rastreo <strong class="font-mono text-white">{{ $order->tracking_code }}</strong>.
                        </p>
                    </div>

                    <div class="text-right">
                        <span class="inline-flex rounded-full border px-3.5 py-1 text-xs font-bold {{ $order->status->badgeClasses() }}">
                            {{ $order->status->label() }}
                        </span>
                        <p class="mt-2 font-serif text-3xl font-bold text-white">
                            S/ {{ number_format((float) $order->total, 2) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-8 p-6 sm:p-10">
                {{-- Key Action Buttons --}}
                <div class="flex flex-wrap gap-3">
                    <a
                        href="{{ route('tracking.index', ['codigo' => $order->order_number]) }}"
                        class="btn-glamora-primary"
                    >
                        Seguir Pedido en Vivo &rarr;
                    </a>

                    <a
                        href="{{ route('checkout.confirmation.pdf', $order->order_number) }}"
                        class="btn-glamora-outline"
                    >
                        Descargar Nota de Pedido PDF
                    </a>

                    @auth
                        @if ($order->user_id === auth()->id())
                            <a href="{{ route('account.orders.show', $order->order_number) }}" class="btn-glamora-outline">
                                Ver en Mi Cuenta
                            </a>
                        @endif
                    @endauth
                </div>

                {{-- Shipping & Payment Summary --}}
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-glamora-blush/35 bg-glamora-cream/70 p-5">
                        <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">Entrega &amp; Facturación</h2>
                        <dl class="mt-3 space-y-1.5 text-sm text-glamora-muted">
                            <div><strong class="text-glamora-charcoal">Documento:</strong> {{ $order->document_type }} {{ $order->document_number }}</div>
                            <div><strong class="text-glamora-charcoal">Correo:</strong> {{ $order->customer_email }}</div>
                            <div><strong class="text-glamora-charcoal">Dirección:</strong> {{ $order->shipping_address }}, {{ $order->shipping_district }}, {{ $order->shipping_city }}</div>
                            <div><strong class="text-glamora-charcoal">Envío:</strong> {{ $order->shipping_method->label() }} ({{ $order->shipping_method->estimatedWindow() }})</div>
                        </dl>
                    </div>

                    <div class="rounded-2xl border border-glamora-blush/35 bg-glamora-cream/70 p-5">
                        <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">Pago Registrado</h2>
                        @if ($primaryPayment)
                            <dl class="mt-3 space-y-1.5 text-sm text-glamora-muted">
                                <div><strong class="text-glamora-charcoal">Método:</strong> {{ $primaryPayment->payment_method->label() }}</div>
                                <div>
                                    <strong class="text-glamora-charcoal">Estado:</strong>
                                    <span class="font-semibold {{ $primaryPayment->status === 'verified' ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ $primaryPayment->status === 'verified' ? 'Verificado' : 'Pendiente de conciliación' }}
                                    </span>
                                </div>
                                @if ($primaryPayment->transaction_reference)
                                    <div><strong class="text-glamora-charcoal">Referencia:</strong> <span class="font-mono">{{ $primaryPayment->transaction_reference }}</span></div>
                                @endif
                                @if ($primaryPayment->b2b_due_date)
                                    <div><strong class="text-glamora-charcoal">Vencimiento Crédito B2B:</strong> {{ $primaryPayment->b2b_due_date->format('d/m/Y') }}</div>
                                @endif
                                @if ($primaryPayment->crypto_tx_hash)
                                    <div><strong class="text-glamora-charcoal">Tx Hash ({{ $primaryPayment->crypto_network }}):</strong> <span class="font-mono text-xs break-all">{{ $primaryPayment->crypto_tx_hash }}</span></div>
                                @endif
                            </dl>
                        @endif
                    </div>
                </div>

                {{-- Items with FEFO Batch Allocations --}}
                <div>
                    <h2 class="mb-3 font-serif text-2xl font-semibold text-glamora-charcoal">
                        Ítems &amp; Lotes FEFO Asignados
                    </h2>
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
                                    <div class="mt-1 flex items-center gap-2 text-xs text-glamora-muted">
                                        <span class="inline-block h-3 w-3 rounded-full border border-black/15" style="background-color: {{ $safeHex }}"></span>
                                        <span>{{ $item->variant_name }}</span>
                                        <span class="font-mono">· {{ $item->sku }}</span>
                                        <span>· {{ $item->quantity }} uds. &times; S/ {{ number_format((float) $item->unit_price, 2) }}</span>
                                    </div>
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
                                <p class="font-serif text-xl font-bold text-glamora-charcoal">
                                    S/ {{ number_format((float) $item->subtotal, 2) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
