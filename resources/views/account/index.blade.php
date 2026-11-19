@extends('layouts.storefront')

@section('title', 'Mi Cuenta — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @component('account.partials.shell')
        {{-- KPI Cards --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card-glamora p-5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Nivel Comercial</span>
                <p class="mt-2 font-serif text-2xl font-bold text-glamora-charcoal">{{ $effectiveTier->label() }}</p>
                <p class="mt-1 text-xs text-glamora-muted">
                    {{ $effectiveTier->discountPercentage() > 0 ? $effectiveTier->discountPercentage() . '% descuento base mayorista' : 'Tarifas minoristas y por volumen' }}
                </p>
            </div>

            <div class="card-glamora p-5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Pedidos Registrados</span>
                <p class="mt-2 font-serif text-2xl font-bold text-glamora-charcoal">{{ $ordersCount }}</p>
                <a href="{{ route('account.orders') }}" class="mt-1 inline-block text-xs font-semibold text-glamora-terracotta hover:underline">
                    Ver historial completo &rarr;
                </a>
            </div>

            <div class="card-glamora p-5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Direcciones &amp; RMA</span>
                <p class="mt-2 font-serif text-2xl font-bold text-glamora-charcoal">{{ $addressesCount }} dir. / {{ $activeReturnsCount }} RMA</p>
                <a href="{{ route('account.addresses') }}" class="mt-1 inline-block text-xs font-semibold text-glamora-terracotta hover:underline">
                    Administrar libreta &rarr;
                </a>
            </div>
        </div>

        {{-- B2B Credit Status --}}
        @if ($wholesaleProfile)
            <div class="card-glamora border-glamora-gold/45 p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <span class="badge-glamora-gold">Perfil Mayorista B2B · {{ strtoupper($wholesaleProfile->status) }}</span>
                        <h2 class="mt-2 font-serif text-2xl font-semibold text-glamora-charcoal">
                            {{ $wholesaleProfile->company_name }}
                        </h2>
                        <p class="text-xs text-glamora-muted">
                            RUC {{ $wholesaleProfile->ruc }} · {{ $wholesaleProfile->business_type ?? 'Socio Comercial' }}
                        </p>
                    </div>

                    @if ($wholesaleProfile->status === 'approved')
                        <div class="text-right">
                            <span class="block text-xs text-glamora-muted">Crédito Disponible</span>
                            <span class="font-serif text-3xl font-bold text-emerald-700">
                                S/ {{ number_format($wholesaleProfile->availableCredit(), 2) }}
                            </span>
                            <span class="block text-[11px] text-glamora-muted">
                                Línea S/ {{ number_format((float) $wholesaleProfile->credit_limit, 2) }} · Usado S/ {{ number_format((float) $wholesaleProfile->credit_used, 2) }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Alerts --}}
        @if (count($alerts) > 0)
            <div class="space-y-3">
                @foreach ($alerts as $alert)
                    <div @class([
                        'flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4',
                        'border-amber-200 bg-amber-50/80 text-amber-950' => $alert['type'] === 'warning',
                        'border-sky-200 bg-sky-50/80 text-sky-950' => $alert['type'] === 'info',
                    ])>
                        <div>
                            <p class="text-sm font-bold">{{ $alert['title'] }}</p>
                            <p class="mt-0.5 text-xs opacity-85">{{ $alert['message'] }}</p>
                        </div>
                        <a href="{{ $alert['action_url'] }}" class="btn-glamora-outline px-4 py-1.5 text-xs">
                            {{ $alert['action_label'] }}
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Recent Orders --}}
        <div class="card-glamora overflow-hidden">
            <div class="flex items-center justify-between border-b border-glamora-blush/30 bg-white px-6 py-4">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">Últimos Pedidos</h2>
                <a href="{{ route('account.orders') }}" class="text-xs font-semibold text-glamora-terracotta hover:underline">
                    Ver todos &rarr;
                </a>
            </div>

            @if ($recentOrders->isEmpty())
                <div class="p-8 text-center text-sm text-glamora-muted">
                    Aún no registras pedidos en tu cuenta.
                    <a href="{{ route('catalog.index') }}" class="ml-1 font-semibold text-glamora-terracotta hover:underline">
                        Explorar catálogo
                    </a>
                </div>
            @else
                <div class="divide-y divide-glamora-blush/25 bg-white">
                    @foreach ($recentOrders as $order)
                        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <a href="{{ route('account.orders.show', $order->order_number) }}" class="font-mono text-sm font-bold text-glamora-charcoal hover:text-glamora-terracotta">
                                        {{ $order->order_number }}
                                    </a>
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $order->status->badgeClasses() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-glamora-muted">
                                    {{ $order->created_at?->format('d/m/Y H:i') }} · {{ $order->items->count() }} ítem(s) · {{ $order->shipping_method->label() }}
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="font-serif text-lg font-bold text-glamora-charcoal">
                                    S/ {{ number_format((float) $order->total, 2) }}
                                </span>
                                <a href="{{ route('account.orders.show', $order->order_number) }}" class="btn-glamora-outline px-3.5 py-1.5 text-xs">
                                    Detalle
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endcomponent
@endsection
