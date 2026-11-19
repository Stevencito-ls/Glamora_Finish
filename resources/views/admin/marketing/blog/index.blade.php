@extends('layouts.admin')

@section('title', 'Blog Editorial — Marketing Glamora Perú')
@section('page_title', 'Blog Editorial & Shop the Story')
@section('page_subtitle', 'Crea guías cosméticas, controla su publicación en Storefront/Sitemap y vincula productos en post_product.')

@section('header_actions')
    <a href="{{ route('admin.marketing.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Hub Marketing
    </a>
    <a href="{{ route('admin.marketing.blog.create') }}" class="btn-glamora-primary py-2 px-4 text-xs">
        + Nuevo Artículo
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.marketing.blog.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar título, slug o etiqueta..."
                class="input-glamora"
            >
            <select name="tag" class="input-glamora">
                <option value="">Todas las etiquetas</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag }}" @selected($tagFilter === $tag)>
                        {{ $tag }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="input-glamora">
                <option value="">Todos los estados</option>
                <option value="published" @selected($statusFilter === 'published')>Publicados</option>
                <option value="draft" @selected($statusFilter === 'draft')>Borradores</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.marketing.blog.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">Limpiar</a>
            </div>
        </form>

        {{-- Table --}}
        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Artículo</th>
                            <th class="px-5 py-3 text-left">Etiqueta</th>
                            <th class="px-5 py-3 text-center">Productos</th>
                            <th class="px-5 py-3 text-left">Publicación</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($posts as $post)
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <img
                                            src="{{ $post->cover_url }}"
                                            alt="{{ $post->title }}"
                                            class="h-12 w-20 shrink-0 rounded-lg border border-glamora-blush/30 bg-glamora-cream object-cover"
                                        >
                                        <div class="min-w-0">
                                            <p class="font-semibold text-glamora-charcoal">{{ $post->title }}</p>
                                            <p class="font-mono text-[11px] text-glamora-muted">/{{ $post->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="rounded-full bg-glamora-cream px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">
                                        {{ $post->category_tag }}
                                    </span>
                                    <span class="mt-1 block text-[11px] text-glamora-muted">{{ $post->reading_time_minutes }} min</span>
                                </td>
                                <td class="px-5 py-3.5 text-center font-bold text-glamora-charcoal">
                                    {{ $post->products_count }}
                                </td>
                                <td class="px-5 py-3.5 text-[11px] text-glamora-muted">
                                    <div>{{ $post->published_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</div>
                                    <div>Autor: {{ $post->author?->name ?? 'Editorial Glamora' }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if ($post->is_published && $post->published_at !== null && $post->published_at->lte(now()))
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">Publicado</span>
                                    @elseif ($post->is_published)
                                        <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-[10px] font-bold text-sky-700">Programado</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">Borrador</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @if ($post->is_published && $post->published_at !== null && $post->published_at->lte(now()))
                                            <a href="{{ route('blog.show', $post->slug) }}" target="_blank" rel="noopener" class="rounded-full border border-glamora-gold/60 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:bg-glamora-gold/20">
                                                Ver
                                            </a>
                                        @endif

                                        <form action="{{ route('admin.marketing.blog.toggle-publish', $post) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal transition hover:bg-glamora-charcoal hover:text-glamora-cream">
                                                {{ $post->is_published ? 'Pasar a Borrador' : 'Publicar' }}
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.marketing.blog.edit', $post) }}" class="rounded-full border border-glamora-terracotta/40 px-3 py-1 text-[11px] font-semibold text-glamora-terracotta transition hover:bg-glamora-terracotta hover:text-white">
                                            Editar
                                        </a>

                                        <form action="{{ route('admin.marketing.blog.destroy', $post) }}" method="POST" data-confirm="¿Eliminar el artículo {{ $post->title }}?" onsubmit="return confirm(this.dataset.confirm);">
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
                                    No hay artículos registrados en el blog editorial.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($posts->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
