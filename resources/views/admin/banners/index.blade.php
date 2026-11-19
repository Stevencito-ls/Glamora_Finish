@extends('layouts.admin')

@section('title', 'Banners y Multimedia — Glamora Perú')
@section('page_title', 'Banners Editoriales & Centro Multimedia')
@section('page_subtitle', 'Administra el Hero Slider de portada, banners promocionales secundarios, portada mayorista B2B y fotografías subidas en storage.')

@section('header_actions')
    <a href="{{ route('admin.banners.create') }}" class="btn-glamora-primary py-2 px-4 text-xs">
        + Nuevo Banner Editorial
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- KPIs de Posiciones --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Total Banners</p>
                <p class="mt-1 font-serif text-2xl font-semibold text-glamora-charcoal">{{ $stats['total'] }}</p>
                <p class="mt-0.5 text-[11px] text-glamora-muted">{{ $stats['active'] }} activos en tienda</p>
            </div>
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Hero Principal (Home)</p>
                <p class="mt-1 font-serif text-2xl font-semibold text-glamora-charcoal">{{ $stats['hero_principal'] }}</p>
                <p class="mt-0.5 text-[11px] text-glamora-muted">Slides rotativos portada</p>
            </div>
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Promo Secundario</p>
                <p class="mt-1 font-serif text-2xl font-semibold text-glamora-charcoal">{{ $stats['promo_secundario'] }}</p>
                <p class="mt-0.5 text-[11px] text-glamora-muted">Destacados campaña</p>
            </div>
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-stone-700">Portal Mayorista B2B</p>
                <p class="mt-1 font-serif text-2xl font-semibold text-glamora-charcoal">{{ $stats['portal_mayorista'] }}</p>
                <p class="mt-0.5 text-[11px] text-glamora-muted">Cabecera /mayoristas</p>
            </div>
            <div class="card-glamora p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Archivos en Storage</p>
                <p class="mt-1 font-serif text-2xl font-semibold text-glamora-charcoal">{{ count($uploadedMedia) }}</p>
                <p class="mt-0.5 text-[11px] text-glamora-muted">Disco public verificado</p>
            </div>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('admin.banners.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar titular, subtítulo o etiqueta..."
                class="input-glamora"
            >
            <select name="position" class="input-glamora">
                <option value="">Todas las ubicaciones</option>
                @foreach ($positions as $pos)
                    <option value="{{ $pos->value }}" @selected($positionFilter === $pos->value)>
                        {{ $pos->label() }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="input-glamora">
                <option value="">Todos los estados</option>
                <option value="active" @selected($statusFilter === 'active')>Activos</option>
                <option value="inactive" @selected($statusFilter === 'inactive')>Inactivos</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.banners.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">Limpiar</a>
            </div>
        </form>

        {{-- Tabla de Banners --}}
        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Vista Previa</th>
                            <th class="px-5 py-3 text-left">Titular &amp; Copy Editorial</th>
                            <th class="px-5 py-3 text-left">Ubicación</th>
                            <th class="px-5 py-3 text-center">Orden</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($banners as $banner)
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <img
                                            src="{{ $banner->image_url }}"
                                            alt="{{ $banner->title }}"
                                            class="h-14 w-20 rounded-xl border border-glamora-blush/35 bg-glamora-cream object-cover shadow-2xs"
                                        >
                                        @if ($banner->accent_image_path)
                                            <img
                                                src="{{ $banner->accent_image_url }}"
                                                alt="Detalle {{ $banner->title }}"
                                                title="Imagen flotante secundaria"
                                                class="h-10 w-10 rounded-lg border border-glamora-gold/40 bg-glamora-cream object-cover"
                                            >
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($banner->eyebrow)
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">
                                            {{ $banner->eyebrow }}
                                        </span>
                                    @endif
                                    <p class="font-semibold text-glamora-charcoal">{{ $banner->title }}</p>
                                    @if ($banner->subtitle)
                                        <p class="mt-0.5 line-clamp-1 max-w-md text-[11px] text-glamora-muted">{{ $banner->subtitle }}</p>
                                    @endif
                                    @if ($banner->button_text)
                                        <p class="mt-1 font-mono text-[10px] text-glamora-muted">
                                            CTA: <span class="font-semibold text-glamora-charcoal">{{ $banner->button_text }}</span> &rarr; {{ $banner->button_url }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $banner->position->badgeClasses() }}">
                                        {{ $banner->position->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center font-mono font-semibold text-glamora-charcoal">
                                    {{ $banner->sort_order }}
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <form action="{{ route('admin.banners.toggle', $banner) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold transition {{ $banner->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                                        >
                                            {{ $banner->is_active ? 'Activo' : 'Inactivo' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('admin.banners.edit', $banner) }}" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:bg-glamora-charcoal hover:text-glamora-cream">
                                            Editar
                                        </a>
                                        <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" data-confirm="¿Eliminar el banner «{{ $banner->title }}»?" onsubmit="return confirm(this.dataset.confirm);">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-[11px] font-semibold text-rose-700 transition hover:bg-rose-50">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No se encontraron banners registrados con los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($banners->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $banners->links() }}
                </div>
            @endif
        </div>

        {{-- Biblioteca Rápida de Archivos Subidos en Storage --}}
        <div class="card-glamora p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-glamora-blush/25 pb-4">
                <div>
                    <h2 class="font-serif text-lg font-semibold text-glamora-charcoal">
                        Repositorio Multimedia en Disco Local (storage/app/public)
                    </h2>
                    <p class="text-xs text-glamora-muted">
                        Fotografías reales subidas desde Productos, Variantes, Categorías, Marcas, Blog y Banners.
                    </p>
                </div>
                <span class="rounded-full bg-glamora-cream px-3 py-1 text-[11px] font-semibold text-glamora-charcoal">
                    JPG · PNG · WEBP (Máx. 4 MB)
                </span>
            </div>

            @if ($uploadedMedia === [])
                <p class="py-6 text-center text-xs text-glamora-muted">
                    Aún no se han subido archivos locales adicionales a <code class="font-mono">storage/app/public</code>. Las imágenes del catálogo utilizan fotografías cosméticas de alta resolución y cualquier archivo que subas desde los formularios aparecerá aquí automáticamente.
                </p>
            @else
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($uploadedMedia as $media)
                        <div class="group overflow-hidden rounded-2xl border border-glamora-blush/30 bg-white p-2 shadow-2xs">
                            <div class="aspect-square overflow-hidden rounded-xl bg-glamora-cream">
                                <img
                                    src="{{ $media['url'] }}"
                                    alt="{{ $media['filename'] }}"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    loading="lazy"
                                >
                            </div>
                            <div class="mt-2">
                                <span class="inline-block rounded-md bg-glamora-cream px-1.5 py-0.5 text-[9px] font-bold uppercase text-glamora-terracotta">
                                    {{ $media['folder'] }}
                                </span>
                                <p class="mt-1 truncate font-mono text-[10px] text-glamora-charcoal" title="{{ $media['filename'] }}">
                                    {{ $media['filename'] }}
                                </p>
                                <p class="text-[10px] text-glamora-muted">{{ $media['size_kb'] }} KB</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
