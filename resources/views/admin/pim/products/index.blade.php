@extends('layouts.admin')

@section('title', 'Productos PIM — Glamora Perú')
@section('page_title', 'Productos, Variantes & Precios B2B')
@section('page_subtitle', 'Catálogo maestro con registro sanitario DIGEMID NSOC, tonos hexadecimales, puntos de reorden y escalas de precio.')

@section('header_actions')
    <li class="nav-item">
        <a
            href="{{ route('admin.pim.catalog-pdf') }}"
            target="_blank"
            rel="noopener"
            class="btn btn-sm btn-outline-dark rounded-pill px-3 text-xs font-semibold"
        >
            <i class="fas fa-file-pdf me-1 text-danger"></i> Descargar Catálogo PDF
        </a>
    </li>
    @can('catalog.create')
        <li class="nav-item">
            <a href="{{ route('admin.pim.products.create') }}" class="btn btn-sm btn-dark rounded-pill px-3 text-xs font-semibold">
                + Nuevo Producto
            </a>
        </li>
    @endcan
@endsection

@section('content')
    <div class="space-y-6">
        <form method="GET" action="{{ route('admin.pim.products.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar nombre, NSOC, SKU o tono..."
                class="input-glamora lg:col-span-2"
            >
            <select name="category_id" class="input-glamora">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) $cat->id === (string) $categoryId)>
                        {{ $cat->parent ? $cat->parent->name . ' › ' : '' }}{{ $cat->name }}
                    </option>
                @endforeach
            </select>
            <select name="brand_id" class="input-glamora">
                <option value="">Todas las marcas</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string) $brand->id === (string) $brandId)>
                        {{ $brand->name }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="input-glamora">
                <option value="">Activos e Inactivos</option>
                <option value="active" @selected($statusFilter === 'active')>Solo Activos</option>
                <option value="inactive" @selected($statusFilter === 'inactive')>Solo Inactivos</option>
                <option value="trashed" @selected($statusFilter === 'trashed')>Archivados (Papelera)</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.pim.products.index') }}" class="btn-glamora-outline py-2 px-3 text-xs">Limpiar</a>
            </div>
        </form>

        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Producto / Marca</th>
                            <th class="px-5 py-3 text-left">DIGEMID NSOC</th>
                            <th class="px-5 py-3 text-center">Variantes</th>
                            <th class="px-5 py-3 text-center">Stock FEFO / Reorden</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($products as $product)
                            @php
                                $avail = (int) ($stockByProduct[$product->id] ?? 0);
                                $isLow = $avail <= (int) $product->reorder_point;
                            @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <img
                                            src="{{ $product->image_url }}"
                                            alt="{{ $product->name }}"
                                            class="h-11 w-11 shrink-0 rounded-xl border border-glamora-blush/35 bg-glamora-cream object-cover shadow-2xs"
                                        >
                                        <div>
                                            <p class="font-semibold text-glamora-charcoal">{{ $product->name }}</p>
                                            <p class="text-[11px] text-glamora-muted">
                                                {{ $product->brand?->name }} · {{ $product->category?->name }}
                                                @if ($product->is_b2b_kit)
                                                    <span class="ml-1 rounded bg-glamora-charcoal px-1.5 py-0.5 text-[9px] font-bold uppercase text-glamora-gold">Kit B2B</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-[11px] text-glamora-charcoal">
                                    {{ $product->sanitary_registration ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-center font-semibold">
                                    {{ $product->variants_count }}
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span @class([
                                        'inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold',
                                        'border-amber-200 bg-amber-50 text-amber-800' => $isLow,
                                        'border-emerald-200 bg-emerald-50 text-emerald-700' => ! $isLow,
                                    ])>
                                        {{ $avail }} uds. (mín {{ $product->min_stock_alert }} / reo {{ $product->reorder_point }})
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if ($product->trashed())
                                        <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700">Archivado</span>
                                    @elseif ($product->is_active)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">Activo</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($product->trashed())
                                            @can('catalog.delete')
                                                <form action="{{ route('admin.pim.products.restore', $product->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50">
                                                        Restaurar
                                                    </button>
                                                </form>
                                            @endcan
                                        @else
                                            <a href="{{ route('admin.pim.products.edit', $product) }}" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:bg-glamora-charcoal hover:text-glamora-cream">
                                                {{ auth()->user()->can('catalog.edit') ? 'Editar & Variantes' : 'Ver Ficha' }}
                                            </a>
                                            @can('catalog.delete')
                                                <form action="{{ route('admin.pim.products.destroy', $product) }}" method="POST" data-confirm="¿Archivar el producto {{ $product->name }}?" onsubmit="return confirm(this.dataset.confirm);">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-[11px] font-semibold text-rose-700 transition hover:bg-rose-50">
                                                        Archivar
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No se encontraron productos con los filtros actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
