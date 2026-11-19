@extends('layouts.admin')

@section('title', ($isEdit ? 'Editar Artículo' : 'Nuevo Artículo') . ' — Blog Glamora Perú')
@section('page_title', $isEdit ? 'Editar Artículo: ' . $post->title : 'Crear Artículo Editorial')
@section('page_subtitle', 'Redacta la guía cosmética, optimiza metadatos SEO y vincula productos Shop the Story.')

@section('header_actions')
    <a href="{{ route('admin.marketing.blog.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Volver al Blog
    </a>
@endsection

@section('content')
    <form
        action="{{ $isEdit ? route('admin.marketing.blog.update', $post) : route('admin.marketing.blog.store') }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="{
            coverPreview: '{{ $isEdit ? $post->cover_url : '' }}',
            onCoverFile(e) {
                const f = e.target.files[0];
                if (f) {
                    this.coverPreview = URL.createObjectURL(f);
                }
            }
        }"
        class="mx-auto max-w-5xl space-y-6"
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        {{-- Core Editorial Data --}}
        <div class="card-glamora space-y-5 p-6 sm:p-8">
            <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">Contenido Principal</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="post_title" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Título Editorial *
                    </label>
                    <input id="post_title" type="text" name="title" value="{{ old('title', $post->title) }}" required class="input-glamora">
                </div>

                <div>
                    <label for="post_slug" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Slug URL (opcional)
                    </label>
                    <input id="post_slug" type="text" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="se-genera-automaticamente" class="input-glamora font-mono">
                </div>

                <div>
                    <label for="post_tag" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Etiqueta / Categoría *
                    </label>
                    <input id="post_tag" type="text" name="category_tag" value="{{ old('category_tag', $post->category_tag) }}" placeholder="Skincare, Maquillaje, Profesional B2B..." required class="input-glamora">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="post_reading_time" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Minutos Lectura
                        </label>
                        <input id="post_reading_time" type="number" min="1" max="120" name="reading_time_minutes" value="{{ old('reading_time_minutes', $post->reading_time_minutes ?? 4) }}" class="input-glamora">
                    </div>
                    <div>
                        <label for="post_published_at" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Fecha Publicación
                        </label>
                        <input id="post_published_at" type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="input-glamora">
                    </div>
                </div>
            </div>

            <div>
                <label for="post_excerpt" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Resumen / Extracto Editorial
                </label>
                <textarea id="post_excerpt" name="excerpt" rows="2" class="input-glamora">{{ old('excerpt', $post->excerpt) }}</textarea>
            </div>

            <div>
                <label for="post_content" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Cuerpo del Artículo (HTML editorial permitido: p, h3, h4, ul, ol, li, strong, em, blockquote) *
                </label>
                <textarea id="post_content" name="content" rows="8" required class="input-glamora font-mono text-xs">{{ old('content', $post->content) }}</textarea>
            </div>

            <div class="grid gap-5 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4 sm:grid-cols-2">
                <div class="space-y-3">
                    <label for="post_cover_file" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Subir Portada Editorial (JPG, PNG, WEBP — Máx. 4 MB)
                    </label>
                    <template x-if="coverPreview">
                        <img :src="coverPreview" alt="Preview portada" class="h-36 w-full rounded-xl border border-glamora-blush/40 bg-white object-cover">
                    </template>
                    <input
                        id="post_cover_file"
                        type="file"
                        name="cover_image_file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onCoverFile($event)"
                        class="input-glamora bg-white text-xs"
                    >
                </div>
                <div>
                    <label for="post_cover_path" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        O URL / Ruta de Portada Existente
                    </label>
                    <input id="post_cover_path" type="text" name="cover_image" value="{{ old('cover_image', $post->cover_image) }}" placeholder="https://images.unsplash.com/photo-..." class="input-glamora bg-white font-mono text-xs">
                </div>
            </div>

            <div class="grid gap-5 border-t border-glamora-blush/30 pt-5 sm:grid-cols-2">
                <div>
                    <label for="post_meta_title" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Meta Title (SEO)
                    </label>
                    <input id="post_meta_title" type="text" name="meta_title" value="{{ old('meta_title', $post->meta_title) }}" class="input-glamora">
                </div>
                <div>
                    <label for="post_meta_description" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Meta Description (SEO)
                    </label>
                    <input id="post_meta_description" type="text" name="meta_description" value="{{ old('meta_description', $post->meta_description) }}" class="input-glamora">
                </div>
            </div>
        </div>

        {{-- Shop the Story Products Sync --}}
        <div class="card-glamora space-y-4 p-6 sm:p-8">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-glamora-terracotta">Shop the Story</span>
                <h2 class="mt-0.5 font-serif text-2xl font-semibold text-glamora-charcoal">
                    Productos Recomendados en la Guía (<code class="font-mono text-sm">post_product</code>)
                </h2>
                <p class="mt-1 text-xs text-glamora-muted">
                    Selecciona los productos que aparecerán en la barra lateral de compra directa del artículo, su orden y un callout editorial personalizado.
                </p>
            </div>

            <div class="max-h-96 overflow-y-auto rounded-2xl border border-glamora-blush/30">
                <table class="min-w-full divide-y divide-glamora-blush/20 text-xs">
                    <thead class="sticky top-0 bg-glamora-cream text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Incluir / Producto</th>
                            <th class="w-24 px-3 py-2.5 text-center">Orden</th>
                            <th class="px-4 py-2.5 text-left">Callout Editorial (opcional)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/15 bg-white">
                        @foreach ($products as $product)
                            @php
                                $pivot = $selectedProducts[$product->id] ?? null;
                                $isChecked = (bool) old("products.{$product->id}.selected", $pivot !== null);
                                $sortOrder = old("products.{$product->id}.sort_order", $pivot['sort_order'] ?? ($loop->iteration));
                                $callout = old("products.{$product->id}.custom_callout", $pivot['custom_callout'] ?? '');
                            @endphp
                            <tr class="hover:bg-glamora-cream/35">
                                <td class="px-4 py-2.5">
                                    <label class="flex cursor-pointer items-center gap-2.5">
                                        <input
                                            type="checkbox"
                                            name="products[{{ $product->id }}][selected]"
                                            value="1"
                                            @checked($isChecked)
                                            class="h-4 w-4 accent-glamora-terracotta"
                                        >
                                        <span>
                                            <strong class="block text-glamora-charcoal">{{ $product->name }}</strong>
                                            <span class="text-[10px] text-glamora-muted">{{ $product->brand?->name }}</span>
                                        </span>
                                    </label>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <input
                                        type="number"
                                        min="1"
                                        max="999"
                                        name="products[{{ $product->id }}][sort_order]"
                                        value="{{ $sortOrder }}"
                                        class="w-16 rounded-lg border border-glamora-charcoal/15 px-2 py-1 text-center text-xs"
                                    >
                                </td>
                                <td class="px-4 py-2.5">
                                    <input
                                        type="text"
                                        maxlength="180"
                                        name="products[{{ $product->id }}][custom_callout]"
                                        value="{{ $callout }}"
                                        placeholder="Ej. Paso 1 · Antioxidante matutino"
                                        class="w-full rounded-lg border border-glamora-charcoal/15 px-3 py-1.5 text-xs"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Publish & Submit --}}
        <div class="card-glamora flex flex-wrap items-center justify-between gap-4 p-6">
            <label class="inline-flex items-center gap-2.5 text-sm font-medium text-glamora-charcoal">
                <input
                    type="checkbox"
                    name="is_published"
                    value="1"
                    @checked(old('is_published', $post->is_published))
                    class="h-4 w-4 accent-glamora-terracotta"
                >
                Publicar artículo en el Storefront y Sitemap XML
            </label>

            <button type="submit" class="btn-glamora-primary px-8">
                {{ $isEdit ? 'Guardar Cambios' : 'Crear Artículo' }}
            </button>
        </div>
    </form>
@endsection
