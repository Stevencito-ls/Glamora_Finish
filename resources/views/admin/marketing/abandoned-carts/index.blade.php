@extends('layouts.admin')

@section('title', 'Carritos Abandonados — Marketing Glamora Perú')
@section('page_title', 'Carritos Abandonados & Recuperación')
@section('page_subtitle', 'Monitorea carritos pendientes, revisa sus ítems y despacha recordatorios por correo en cola.')

@section('header_actions')
    <a href="{{ route('admin.marketing.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Hub Marketing
    </a>
    <form action="{{ route('admin.marketing.abandoned-carts.dispatch-eligible') }}" method="POST" class="inline-flex">
        @csrf
        <button type="submit" class="btn-glamora-terracotta py-2 px-4 text-xs">
            Despachar Elegibles ({{ $stats['eligible'] }})
        </button>
    </form>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- KPI Strip --}}
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Sin Recuperar</p>
                <p class="mt-1 font-serif text-3xl font-bold text-glamora-charcoal">{{ $stats['unrecovered'] }}</p>
            </div>
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Elegibles (&ge;60m / Cooldown 24h)</p>
                <p class="mt-1 font-serif text-3xl font-bold text-amber-700">{{ $stats['eligible'] }}</p>
            </div>
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Recuperados</p>
                <p class="mt-1 font-serif text-3xl font-bold text-emerald-700">{{ $stats['recovered'] }}</p>
            </div>
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Monto Potencial</p>
                <p class="mt-1 font-serif text-3xl font-bold text-glamora-terracotta">
                    S/ {{ number_format($stats['potential_revenue'], 2) }}
                </p>
            </div>
        </section>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.marketing.abandoned-carts.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-3">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar correo, cliente o sesión..."
                class="input-glamora"
            >
            <select name="status" class="input-glamora">
                <option value="">Todos los carritos</option>
                <option value="eligible" @selected($statusFilter === 'eligible')>Elegibles para recordatorio automático</option>
                <option value="unrecovered" @selected($statusFilter === 'unrecovered')>Sin recuperar</option>
                <option value="reminded" @selected($statusFilter === 'reminded')>Ya recordados (&ge;1 envío)</option>
                <option value="recovered" @selected($statusFilter === 'recovered')>Recuperados</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.marketing.abandoned-carts.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">Limpiar</a>
            </div>
        </form>

        {{-- Table --}}
        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Contacto / Cliente</th>
                            <th class="px-5 py-3 text-left">Contenido de la Bolsa</th>
                            <th class="px-5 py-3 text-right">Subtotal</th>
                            <th class="px-5 py-3 text-left">Última Actividad</th>
                            <th class="px-5 py-3 text-center">Recordatorios</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($carts as $cart)
                            @php
                                $isEligible = in_array($cart->id, $eligibleIds, true);
                                $payloadItems = is_array($cart->cart_payload) ? $cart->cart_payload : [];
                            @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-glamora-charcoal">
                                        {{ $cart->email ?: 'Visitante sin correo' }}
                                    </p>
                                    <p class="text-[11px] text-glamora-muted">
                                        {{ $cart->user?->name ?? 'Sesión invitada' }}
                                    </p>
                                    <p class="font-mono text-[10px] text-glamora-muted/80">{{ \Illuminate\Support\Str::limit($cart->session_id, 24) }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-glamora-charcoal">{{ $cart->items_count }} unidad(es)</p>
                                    <ul class="mt-1 space-y-0.5 text-[11px] text-glamora-muted">
                                        @foreach (array_slice($payloadItems, 0, 2) as $item)
                                            <li>
                                                {{ (int) ($item['quantity'] ?? 1) }}&times; {{ $item['product_name'] ?? ($item['sku'] ?? 'Ítem') }}
                                            </li>
                                        @endforeach
                                        @if (count($payloadItems) > 2)
                                            <li class="italic">+ {{ count($payloadItems) - 2 }} producto(s) más</li>
                                        @endif
                                    </ul>
                                </td>
                                <td class="px-5 py-3.5 text-right font-serif text-base font-bold text-glamora-charcoal">
                                    S/ {{ number_format((float) $cart->subtotal, 2) }}
                                </td>
                                <td class="px-5 py-3.5 text-[11px] text-glamora-muted">
                                    {{ $cart->last_activity_at?->format('d/m/Y H:i') }}
                                    <span class="block text-[10px]">{{ $cart->last_activity_at?->diffForHumans() }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="font-mono font-bold text-glamora-charcoal">{{ $cart->reminder_count }} / 3</span>
                                    @if ($cart->last_reminded_at)
                                        <span class="block text-[10px] text-glamora-muted">
                                            Últ.: {{ $cart->last_reminded_at->format('d/m H:i') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if ($cart->recovered_at !== null)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                            Recuperado
                                        </span>
                                    @elseif ($isEligible)
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">
                                            Elegible
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">
                                            En espera
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    @if ($cart->recovered_at === null && $cart->email && $cart->items_count > 0)
                                        <form action="{{ route('admin.marketing.abandoned-carts.remind', $cart) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="rounded-full border border-glamora-terracotta/45 px-3 py-1 text-[11px] font-semibold text-glamora-terracotta transition hover:bg-glamora-terracotta hover:text-white">
                                                Enviar Recordatorio
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[11px] text-glamora-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No se registran carritos abandonados con los criterios actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($carts->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $carts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
