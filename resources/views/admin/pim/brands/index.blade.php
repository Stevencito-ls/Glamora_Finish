@extends('layouts.admin')

@section('title', 'Marcas PIM — Glamora Perú')
@section('page_title', 'Marcas & Laboratorios Cosméticos')
@section('page_subtitle', 'Administra el portafolio de marcas propias y aliadas, origen y certificación cruelty-free.')

@section('header_actions')
    @can('catalog.create')
        <a href="{{ route('admin.pim.brands.create') }}" class="btn-glamora-primary py-2 px-4 text-xs">
            + Nueva Marca
        </a>
    @endcan
@endsection

@section('content')
    <div class="space-y-6">
        <form method="GET" action="{{ route('admin.pim.brands.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-3">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar marca, país o slug..."
                class="input-glamora"
            >
            <select name="status" class="input-glamora">
                <option value="">Todos los estados</option>
                <option value="active" @selected($statusFilter === 'active')>Activas</option>
                <option value="inactive" @selected($statusFilter === 'inactive')>Inactivas</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.pim.brands.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">Limpiar</a>
            </div>
        </form>

        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Marca</th>
                            <th class="px-5 py-3 text-left">Origen</th>
                            <th class="px-5 py-3 text-center">Atributos</th>
                            <th class="px-5 py-3 text-center">Productos</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($brands as $brand)
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <img
                                            src="{{ $brand->logo_url }}"
                                            alt="{{ $brand->name }}"
                                            class="h-11 w-11 shrink-0 rounded-xl border border-glamora-blush/35 bg-glamora-cream object-cover shadow-2xs"
                                        >
                                        <div>
                                            <p class="font-semibold text-glamora-charcoal">{{ $brand->name }}</p>
                                            <p class="font-mono text-[11px] text-glamora-muted">/{{ $brand->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-glamora-charcoal">
                                    {{ $brand->country_origin }}
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <div class="flex flex-wrap justify-center gap-1">
                                        @if ($brand->is_cruelty_free)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Cruelty-Free</span>
                                        @endif
                                        @if ($brand->is_featured)
                                            <span class="rounded-full bg-glamora-gold/20 px-2 py-0.5 text-[10px] font-semibold text-glamora-charcoal">Destacada</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-center font-bold text-glamora-charcoal">
                                    {{ $brand->products_count }}
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if ($brand->is_active)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">Activa</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">Inactiva</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @can('catalog.edit')
                                            <a href="{{ route('admin.pim.brands.edit', $brand) }}" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:bg-glamora-charcoal hover:text-glamora-cream">
                                                Editar
                                            </a>
                                        @endcan
                                        @can('catalog.delete')
                                            <form action="{{ route('admin.pim.brands.destroy', $brand) }}" method="POST" data-confirm="¿Eliminar la marca {{ $brand->name }}?" onsubmit="return confirm(this.dataset.confirm);">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-[11px] font-semibold text-rose-700 transition hover:bg-rose-50">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No hay marcas registradas con los criterios indicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($brands->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $brands->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
