@extends('layouts.admin')

@section('title', 'Cupones Promocionales — Marketing Glamora Perú')
@section('page_title', 'Cupones Promocionales')
@section('page_subtitle', 'Administra códigos de descuento, vigencia, topes de uso y segmentación por nivel comercial.')

@section('header_actions')
    <a href="{{ route('admin.marketing.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Hub Marketing
    </a>
    <a href="{{ route('admin.marketing.coupons.create') }}" class="btn-glamora-primary py-2 px-4 text-xs">
        + Nuevo Cupón
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- KPIs --}}
        <section class="grid gap-4 sm:grid-cols-3">
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Total Registrados</p>
                <p class="mt-1 font-serif text-3xl font-bold text-glamora-charcoal">{{ $stats['total'] }}</p>
            </div>
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Vigentes Hoy</p>
                <p class="mt-1 font-serif text-3xl font-bold text-emerald-700">{{ $stats['valid_now'] }}</p>
            </div>
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Redenciones Acumuladas</p>
                <p class="mt-1 font-serif text-3xl font-bold text-glamora-terracotta">{{ $stats['total_uses'] }}</p>
            </div>
        </section>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.marketing.coupons.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar código o descripción..."
                class="input-glamora"
            >
            <select name="status" class="input-glamora">
                <option value="">Todos los estados</option>
                <option value="valid" @selected($statusFilter === 'valid')>Vigentes ahora</option>
                <option value="active" @selected($statusFilter === 'active')>Activos</option>
                <option value="inactive" @selected($statusFilter === 'inactive')>Inactivos</option>
                <option value="expired" @selected($statusFilter === 'expired')>Expirados</option>
            </select>
            <select name="tier" class="input-glamora">
                <option value="">Todos los niveles</option>
                <option value="all" @selected($tierFilter === 'all')>Público general (sin nivel)</option>
                @foreach ($customerTiers as $tier)
                    <option value="{{ $tier->value }}" @selected($tierFilter === $tier->value)>
                        {{ $tier->label() }}
                    </option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.marketing.coupons.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">Limpiar</a>
            </div>
        </form>

        {{-- Table --}}
        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Código &amp; Descripción</th>
                            <th class="px-5 py-3 text-left">Descuento</th>
                            <th class="px-5 py-3 text-left">Nivel &amp; Mínimo</th>
                            <th class="px-5 py-3 text-center">Usos</th>
                            <th class="px-5 py-3 text-left">Vigencia</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($coupons as $coupon)
                            @php
                                $isLocked = in_array($coupon->id, $lockedCouponIds, true);
                                $isExpired = $coupon->expires_at !== null && $coupon->expires_at->isPast();
                            @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-lg bg-glamora-charcoal px-2.5 py-1 font-mono text-xs font-bold text-glamora-gold">
                                            {{ $coupon->code }}
                                        </span>
                                        @if ($isLocked)
                                            <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800" title="Cupón usado o referenciado en pedidos">
                                                Con historial
                                            </span>
                                        @endif
                                    </div>
                                    @if ($coupon->description)
                                        <p class="mt-1 max-w-xs text-[11px] text-glamora-muted">{{ $coupon->description }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-glamora-charcoal">
                                    @if ($coupon->discount_type === 'fixed')
                                        S/ {{ number_format((float) $coupon->discount_value, 2) }} fijo
                                    @else
                                        {{ number_format((float) $coupon->discount_value, 2) }}%
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-semibold {{ $coupon->applicable_tier?->badgeClasses() ?? 'border-stone-200 bg-stone-50 text-stone-700' }}">
                                        {{ $coupon->applicable_tier?->label() ?? 'Todos los clientes' }}
                                    </span>
                                    <p class="mt-1 text-[11px] text-glamora-muted">
                                        Mín.: S/ {{ number_format((float) $coupon->min_order_amount, 2) }}
                                    </p>
                                </td>
                                <td class="px-5 py-3.5 text-center font-mono">
                                    <span class="font-bold text-glamora-charcoal">{{ $coupon->used_count }}</span>
                                    <span class="text-glamora-muted">/ {{ $coupon->max_uses ?? '∞' }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-[11px] text-glamora-muted">
                                    <div>Inicio: {{ $coupon->starts_at?->format('d/m/Y H:i') ?? 'Inmediato' }}</div>
                                    <div class="{{ $isExpired ? 'font-semibold text-rose-600' : '' }}">
                                        Fin: {{ $coupon->expires_at?->format('d/m/Y H:i') ?? 'Sin vencimiento' }}
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if ($coupon->is_active && ! $isExpired)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">Activo</span>
                                    @elseif ($isExpired)
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">Expirado</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <form action="{{ route('admin.marketing.coupons.toggle', $coupon) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:bg-glamora-charcoal hover:text-glamora-cream">
                                                {{ $coupon->is_active ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>

                                        @if (! $isLocked)
                                            <a href="{{ route('admin.marketing.coupons.edit', $coupon) }}" class="rounded-full border border-glamora-terracotta/40 px-3 py-1 text-[11px] font-semibold text-glamora-terracotta transition hover:bg-glamora-terracotta hover:text-white">
                                                Editar
                                            </a>
                                            <form action="{{ route('admin.marketing.coupons.destroy', $coupon) }}" method="POST" data-confirm="¿Eliminar el cupón {{ $coupon->code }}?" onsubmit="return confirm(this.dataset.confirm);">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-[11px] font-semibold text-rose-700 transition hover:bg-rose-50">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No se encontraron cupones con los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($coupons->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $coupons->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
