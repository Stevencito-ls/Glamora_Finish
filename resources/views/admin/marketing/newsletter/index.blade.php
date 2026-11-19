@extends('layouts.admin')

@section('title', 'Suscriptores Newsletter — Marketing Glamora Perú')
@section('page_title', 'Newsletter & Segmentos Opt-In')
@section('page_subtitle', 'Administra suscriptores, verificación de consentimiento y segmentación dinámica Ley N.º 29733.')

@section('header_actions')
    <a href="{{ route('admin.marketing.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Hub Marketing
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- KPI Strip --}}
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Total Suscriptores</p>
                <p class="mt-1 font-serif text-3xl font-bold text-glamora-charcoal">{{ $stats['total'] }}</p>
            </div>
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Activos</p>
                <p class="mt-1 font-serif text-3xl font-bold text-emerald-700">{{ $stats['active'] }}</p>
            </div>
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Opt-In Verificado</p>
                <p class="mt-1 font-serif text-3xl font-bold text-glamora-terracotta">{{ $stats['verified'] }}</p>
            </div>
            <div class="card-glamora p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Dados de Baja</p>
                <p class="mt-1 font-serif text-3xl font-bold text-glamora-muted">{{ $stats['unsubscribed'] }}</p>
            </div>
        </section>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.marketing.newsletter.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar correo o nombre..."
                class="input-glamora"
            >
            <select name="segment" class="input-glamora">
                <option value="">Todos los segmentos</option>
                @foreach ($segments as $seg)
                    <option value="{{ $seg }}" @selected($segmentFilter === $seg)>
                        {{ ucfirst($seg) }}
                    </option>
                @endforeach
            </select>
            <select name="active" class="input-glamora">
                <option value="">Estado (Todos)</option>
                <option value="1" @selected($activeFilter === '1')>Activos</option>
                <option value="0" @selected($activeFilter === '0')>Inactivos / Baja</option>
            </select>
            <select name="verified" class="input-glamora">
                <option value="">Opt-In (Todos)</option>
                <option value="1" @selected($verifiedFilter === '1')>Verificados</option>
                <option value="0" @selected($verifiedFilter === '0')>Pendientes</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.marketing.newsletter.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">Limpiar</a>
            </div>
        </form>

        {{-- Table --}}
        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Suscriptor</th>
                            <th class="px-5 py-3 text-left">Segmento</th>
                            <th class="px-5 py-3 text-left">Token Opt-In</th>
                            <th class="px-5 py-3 text-center">Verificación</th>
                            <th class="px-5 py-3 text-center">Suscripción</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($subscribers as $subscriber)
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-glamora-charcoal">{{ $subscriber->email }}</p>
                                    <p class="text-[11px] text-glamora-muted">{{ $subscriber->name ?: 'Sin nombre registrado' }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="rounded-full border border-glamora-blush/50 bg-glamora-cream px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-glamora-charcoal">
                                        {{ $subscriber->segment }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-[11px] text-glamora-muted">
                                    {{ $subscriber->opt_in_token ? \Illuminate\Support\Str::limit($subscriber->opt_in_token, 14, '...') : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if ($subscriber->is_verified)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">Verificado</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">Pendiente</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if ($subscriber->is_active)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">Activo</span>
                                        @if ($subscriber->subscribed_at)
                                            <span class="mt-0.5 block text-[10px] text-glamora-muted">{{ $subscriber->subscribed_at->format('d/m/Y') }}</span>
                                        @endif
                                    @else
                                        <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700">Baja</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <form action="{{ route('admin.marketing.newsletter.toggle-verified', $subscriber) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-full border border-glamora-gold/60 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:bg-glamora-gold/20">
                                                {{ $subscriber->is_verified ? 'Quitar Opt-In' : 'Verificar Opt-In' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.marketing.newsletter.toggle-active', $subscriber) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:bg-glamora-charcoal hover:text-glamora-cream">
                                                {{ $subscriber->is_active ? 'Dar de Baja' : 'Reactivar' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No se encontraron suscriptores con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($subscribers->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $subscribers->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
