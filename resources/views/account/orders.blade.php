@extends('layouts.storefront')

@section('title', 'Mis Pedidos — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @component('account.partials.shell')
        <div class="card-glamora overflow-hidden">
            <div class="border-b border-glamora-blush/30 bg-white px-6 py-5">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                    Historial de Pedidos
                </h2>
                <p class="mt-1 text-xs text-glamora-muted">
                    Consulta el detalle de tus compras, lotes FEFO asignados y descarga tus notas de pedido en PDF.
                </p>
            </div>

            @if ($orders->isEmpty())
                <div class="p-10 text-center text-sm text-glamora-muted">
                    No tienes pedidos registrados todavía.
                    <div class="mt-4">
                        <a href="{{ route('catalog.index') }}" class="btn-glamora-primary">Ir al Catálogo</a>
                    </div>
                </div>
            @else
                <div class="divide-y divide-glamora-blush/25 bg-white">
                    @foreach ($orders as $order)
                        @php
                            $payment = $order->payments->first();
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                            <div>
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <a href="{{ route('account.orders.show', $order->order_number) }}" class="font-mono text-base font-bold text-glamora-charcoal hover:text-glamora-terracotta">
                                        {{ $order->order_number }}
                                    </a>
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-bold {{ $order->status->badgeClasses() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                    @if ($order->is_b2b)
                                        <span class="rounded-full bg-glamora-charcoal px-2.5 py-0.5 text-[10px] font-bold uppercase text-glamora-gold">
                                            B2B
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1.5 text-xs text-glamora-muted">
                                    Fecha: {{ $order->created_at?->format('d/m/Y H:i') }}
                                    · Tracking: <span class="font-mono">{{ $order->tracking_code ?? '—' }}</span>
                                    @if ($payment)
                                        · Pago: {{ $payment->payment_method->label() }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="mr-2 font-serif text-xl font-bold text-glamora-charcoal">
                                    S/ {{ number_format((float) $order->total, 2) }}
                                </span>
                                <a href="{{ route('account.orders.show', $order->order_number) }}" class="btn-glamora-outline px-4 py-1.5 text-xs">
                                    Ver Detalle
                                </a>
                                <a href="{{ route('account.orders.pdf', $order->order_number) }}" class="btn-glamora-primary px-4 py-1.5 text-xs">
                                    PDF
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($orders->hasPages())
                    <div class="border-t border-glamora-blush/30 bg-glamora-cream/40 px-6 py-4">
                        {{ $orders->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endcomponent
@endsection
