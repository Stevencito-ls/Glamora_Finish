@extends('layouts.admin')

@section('title', 'Ficha PIM: ' . $product->name . ' — Glamora Perú')
@section('page_title', $product->name)
@section('page_subtitle', 'Ficha sanitaria DIGEMID (' . ($product->sanitary_registration ?? 'Sin NSOC') . '), galería fotográfica, variantes con imagen propia y precios escalonados B2B.')

@section('header_actions')
    <a href="{{ route('admin.pim.products.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Catálogo PIM
    </a>
@endsection

@section('content')
    <div class="space-y-8">
        {{-- 1. Product Master Form --}}
        <form
            action="{{ route('admin.pim.products.update', $product) }}"
            method="POST"
            enctype="multipart/form-data"
            x-data="{
                mainPreview: '{{ $product->image_url }}',
                galleryPreviews: [],
                onMainFile(e) {
                    const file = e.target.files[0];
                    if (file) {
                        this.mainPreview = URL.createObjectURL(file);
                    }
                },
                onGalleryFiles(e) {
                    this.galleryPreviews = Array.from(e.target.files || []).map(f => URL.createObjectURL(f));
                }
            }"
            class="card-glamora space-y-5 p-6"
        >
            @csrf
            @method('PUT')

            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-glamora-blush/30 pb-3">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                    Ficha Técnica, Galería &amp; SEO del Producto
                </h2>
                <span class="font-mono text-xs text-glamora-muted">ID #{{ $product->id }} · /{{ $product->slug }}</span>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="edit_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Nombre *</label>
                    <input id="edit_name" type="text" name="name" value="{{ old('name', $product->name) }}" required class="input-glamora">
                </div>
                <div>
                    <label for="edit_slug" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Slug URL</label>
                    <input id="edit_slug" type="text" name="slug" value="{{ old('slug', $product->slug) }}" class="input-glamora font-mono">
                </div>
                <div>
                    <label for="edit_nsoc" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Registro Sanitario DIGEMID *</label>
                    <input id="edit_nsoc" type="text" name="sanitary_registration" value="{{ old('sanitary_registration', $product->sanitary_registration) }}" required class="input-glamora font-mono uppercase">
                </div>

                <div>
                    <label for="edit_cat" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Categoría *</label>
                    <select id="edit_cat" name="category_id" required class="input-glamora">
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('category_id', $product->category_id) === (string) $cat->id)>
                                {{ $cat->parent ? $cat->parent->name . ' › ' : '' }}{{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="edit_brand" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Marca *</label>
                    <select id="edit_brand" name="brand_id" required class="input-glamora">
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected((string) old('brand_id', $product->brand_id) === (string) $brand->id)>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit_min" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Mín. Alerta *</label>
                        <input id="edit_min" type="number" min="0" name="min_stock_alert" value="{{ old('min_stock_alert', $product->min_stock_alert) }}" required class="input-glamora">
                    </div>
                    <div>
                        <label for="edit_reorder" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Reorden *</label>
                        <input id="edit_reorder" type="number" min="0" name="reorder_point" value="{{ old('reorder_point', $product->reorder_point) }}" required class="input-glamora">
                    </div>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="edit_short" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Resumen Corto</label>
                    <input id="edit_short" type="text" name="short_description" value="{{ old('short_description', $product->short_description) }}" class="input-glamora">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit_skin" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Tipo Piel</label>
                        <input id="edit_skin" type="text" name="skin_type" value="{{ old('skin_type', $product->skin_type) }}" class="input-glamora">
                    </div>
                    <div>
                        <label for="edit_finish" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Acabado</label>
                        <input id="edit_finish" type="text" name="finish_type" value="{{ old('finish_type', $product->finish_type) }}" class="input-glamora">
                    </div>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-3">
                <div>
                    <label for="edit_desc" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Descripción Completa</label>
                    <textarea id="edit_desc" name="description" rows="3" class="input-glamora">{{ old('description', $product->description) }}</textarea>
                </div>
                <div>
                    <label for="edit_ingredients" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Ingredientes (INCI)</label>
                    <textarea id="edit_ingredients" name="ingredients" rows="3" class="input-glamora">{{ old('ingredients', $product->ingredients) }}</textarea>
                </div>
                <div>
                    <label for="edit_how" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Modo de Uso</label>
                    <textarea id="edit_how" name="how_to_use" rows="3" class="input-glamora">{{ old('how_to_use', $product->how_to_use) }}</textarea>
                </div>
            </div>

            {{-- Fotografía Principal + Galería con Vista Previa Alpine.js --}}
            <div class="grid gap-6 border-t border-glamora-blush/30 pt-5 lg:grid-cols-2">
                <div class="space-y-3 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                    <label for="edit_img_file" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Fotografía Principal del Producto (JPG, PNG, WEBP — Máx. 4 MB)
                    </label>
                    <div class="flex items-start gap-4">
                        <img :src="mainPreview" alt="{{ $product->name }}" class="h-28 w-28 shrink-0 rounded-2xl border border-glamora-blush/40 bg-white object-cover shadow-2xs">
                        <div class="min-w-0 flex-1 space-y-2.5">
                            <input
                                id="edit_img_file"
                                type="file"
                                name="main_image_file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="onMainFile($event)"
                                class="input-glamora bg-white text-xs"
                            >
                            <div>
                                <label for="edit_img_path" class="mb-1 block text-[11px] font-semibold text-glamora-muted">URL / Ruta Imagen Actual</label>
                                <input id="edit_img_path" type="text" name="main_image" value="{{ old('main_image', $product->main_image) }}" class="input-glamora bg-white font-mono text-xs">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                    <label for="edit_gallery_files" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Galería Fotográfica ({{ count($product->gallery_urls) }} fotos activas)
                    </label>

                    @php
                        $rawGalleryItems = is_array($product->gallery) ? $product->gallery : [];
                    @endphp
                    @if ($rawGalleryItems !== [])
                        <div class="flex flex-wrap gap-3">
                            @foreach ($rawGalleryItems as $rawImg)
                                @if (is_string($rawImg) && trim($rawImg) !== '')
                                    <label class="group relative block overflow-hidden rounded-xl border border-glamora-blush/40 bg-white p-1">
                                        <img
                                            src="{{ \App\Support\CosmeticImageResolver::resolve($rawImg, 'product', $product->slug) }}"
                                            alt="Galería"
                                            class="h-16 w-16 rounded-lg object-cover"
                                        >
                                        <span class="mt-1 flex items-center justify-center gap-1 text-[10px] font-semibold text-rose-700">
                                            <input type="checkbox" name="remove_gallery[]" value="{{ $rawImg }}" class="h-3 w-3 accent-rose-600">
                                            Quitar
                                        </span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <template x-if="galleryPreviews.length > 0">
                        <div>
                            <p class="mb-1 text-[10px] font-bold uppercase text-emerald-700">Nuevas fotos a agregar:</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(src, idx) in galleryPreviews" :key="idx">
                                    <img :src="src" alt="Nueva foto galería" class="h-16 w-16 rounded-xl border border-emerald-300 bg-white object-cover">
                                </template>
                            </div>
                        </div>
                    </template>

                    <input
                        id="edit_gallery_files"
                        type="file"
                        name="gallery_files[]"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        @change="onGalleryFiles($event)"
                        class="input-glamora bg-white text-xs"
                    >
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="edit_meta_title" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Meta Title</label>
                    <input id="edit_meta_title" type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" class="input-glamora">
                </div>
                <div>
                    <label for="edit_meta_desc" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Meta Description</label>
                    <input id="edit_meta_desc" type="text" name="meta_description" value="{{ old('meta_description', $product->meta_description) }}" class="input-glamora">
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-glamora-blush/30 pt-4">
                <div class="flex flex-wrap gap-5 text-sm">
                    <label class="inline-flex items-center gap-2 font-medium">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="h-4 w-4 accent-glamora-terracotta">
                        Activo
                    </label>
                    <label class="inline-flex items-center gap-2 font-medium">
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured)) class="h-4 w-4 accent-glamora-terracotta">
                        Destacado
                    </label>
                    <label class="inline-flex items-center gap-2 font-medium">
                        <input type="checkbox" name="is_new_launch" value="1" @checked(old('is_new_launch', $product->is_new_launch)) class="h-4 w-4 accent-glamora-terracotta">
                        Nuevo Lanzamiento
                    </label>
                    <label class="inline-flex items-center gap-2 font-medium">
                        <input type="checkbox" name="is_b2b_kit" value="1" @checked(old('is_b2b_kit', $product->is_b2b_kit)) class="h-4 w-4 accent-glamora-terracotta">
                        Kit B2B
                    </label>
                </div>

                @can('catalog.edit')
                    <button type="submit" class="btn-glamora-primary px-6 py-2.5 text-xs">
                        Guardar Ficha del Producto
                    </button>
                @endcan
            </div>
        </form>

        {{-- 2. Variants & Tiered Prices --}}
        <section class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                        Variantes, Tonos Hexadecimales &amp; Precios Escalonados ({{ $product->variants->count() }})
                    </h2>
                    <p class="text-xs text-glamora-muted">
                        Edita cada SKU, fotografía específica del tono, selector visual de color, gramaje/ml, precios B2C/B2B y reglas por volumen y tier.
                    </p>
                </div>
            </div>

            @foreach ($product->variants as $variant)
                @php
                    $safeHex = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($variant->hex_color ?? '')) === 1
                        ? strtoupper((string) $variant->hex_color)
                        : '#C87D72';
                @endphp
                <div
                    x-data="{
                        hex: @js($safeHex),
                        varPreview: '{{ $variant->image_url }}',
                        onVarFile(e) {
                            const f = e.target.files[0];
                            if (f) {
                                this.varPreview = URL.createObjectURL(f);
                            }
                        }
                    }"
                    class="card-glamora overflow-hidden"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-glamora-blush/30 bg-glamora-cream/70 px-6 py-3.5">
                        <div class="flex items-center gap-3">
                            <img :src="varPreview" alt="{{ $variant->shade_name }}" class="h-11 w-11 rounded-xl border border-glamora-blush/40 bg-white object-cover shadow-2xs">
                            <span class="h-6 w-6 rounded-full border border-black/20 shadow-xs" :style="'background-color: ' + hex"></span>
                            <div>
                                <span class="font-mono text-sm font-bold text-glamora-charcoal">{{ $variant->sku }}</span>
                                <span class="ml-2 text-sm font-semibold text-glamora-charcoal">— {{ $variant->shade_name }}</span>
                                @if ($variant->is_default)
                                    <span class="ml-2 rounded-full bg-glamora-gold/25 px-2 py-0.5 text-[10px] font-bold uppercase text-glamora-charcoal">Default</span>
                                @endif
                                @if (! $variant->is_active)
                                    <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-700">Inactiva</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3 text-xs">
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 font-bold text-emerald-800">
                                Stock FEFO disponible: {{ $variant->availableStock() }} uds.
                            </span>

                            @can('catalog.edit')
                                <form action="{{ route('admin.pim.products.variants.destroy', [$product, $variant]) }}" method="POST" data-confirm="¿Eliminar variante {{ $variant->sku }}?" onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-rose-200 bg-white px-3 py-1 font-semibold text-rose-700 hover:bg-rose-50">
                                        Eliminar
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    <div class="space-y-6 bg-white p-6">
                        <form
                            action="{{ route('admin.pim.products.variants.update', [$product, $variant]) }}"
                            method="POST"
                            enctype="multipart/form-data"
                            class="space-y-4"
                        >
                            @csrf
                            @method('PUT')

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">SKU *</label>
                                    <input type="text" name="sku" value="{{ $variant->sku }}" required class="input-glamora font-mono text-xs uppercase">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">Tono / Nombre *</label>
                                    <input type="text" name="shade_name" value="{{ $variant->shade_name }}" required class="input-glamora text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">Color Hexadecimal</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" x-model="hex" class="h-9 w-11 cursor-pointer rounded-lg border border-glamora-charcoal/15 bg-white p-0.5">
                                        <input type="text" name="hex_color" x-model="hex" pattern="^#[0-9A-Fa-f]{6}$" class="input-glamora font-mono text-xs uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">Barcode</label>
                                    <input type="text" name="barcode" value="{{ $variant->barcode }}" class="input-glamora font-mono text-xs">
                                </div>

                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">Gramaje (g)</label>
                                    <input type="number" step="0.01" min="0" name="weight_grams" value="{{ $variant->weight_grams }}" class="input-glamora text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">Volumen (ml)</label>
                                    <input type="number" step="0.01" min="0" name="volume_ml" value="{{ $variant->volume_ml }}" class="input-glamora text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">Precio Lista (S/) *</label>
                                    <input type="number" step="0.01" min="0.10" name="base_price" value="{{ $variant->base_price }}" required class="input-glamora text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">Precio B2B Base / Costo (S/)</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="number" step="0.01" min="0.10" name="wholesale_base_price" value="{{ $variant->wholesale_base_price }}" required title="Precio Base Mayorista" class="input-glamora text-xs">
                                        <input type="number" step="0.01" min="0" name="cost_price" value="{{ $variant->cost_price }}" required title="Costo Unitario" class="input-glamora text-xs">
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 rounded-xl border border-glamora-blush/30 bg-glamora-cream/30 p-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">
                                        Cambiar Fotografía del Tono (JPG, PNG, WEBP — Máx. 4 MB)
                                    </label>
                                    <input
                                        type="file"
                                        name="image_file"
                                        accept="image/jpeg,image/png,image/webp"
                                        @change="onVarFile($event)"
                                        class="input-glamora bg-white text-xs"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">
                                        O URL / Ruta Fotográfica del Tono
                                    </label>
                                    <input
                                        type="text"
                                        name="image_path"
                                        value="{{ $variant->image_path }}"
                                        class="input-glamora bg-white font-mono text-xs"
                                    >
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex gap-5 text-xs">
                                    <label class="inline-flex items-center gap-2 font-medium">
                                        <input type="checkbox" name="is_default" value="1" @checked($variant->is_default) class="h-4 w-4 accent-glamora-terracotta">
                                        Variante Predeterminada
                                    </label>
                                    <label class="inline-flex items-center gap-2 font-medium">
                                        <input type="checkbox" name="is_active" value="1" @checked($variant->is_active) class="h-4 w-4 accent-glamora-terracotta">
                                        Variante Activa
                                    </label>
                                </div>

                                @can('catalog.edit')
                                    <button type="submit" class="rounded-full bg-glamora-charcoal px-4 py-1.5 text-xs font-semibold text-glamora-cream transition hover:bg-glamora-terracotta">
                                        Actualizar Variante
                                    </button>
                                @endcan
                            </div>
                        </form>

                        {{-- Tiered Prices Section --}}
                        <div class="rounded-xl border border-glamora-blush/35 bg-glamora-cream/45 p-4">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                                Precios Escalonados por Tier &amp; Volumen ({{ $variant->tieredPrices->count() }})
                            </h3>

                            @if ($variant->tieredPrices->isNotEmpty())
                                <div class="mt-3 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                                        <thead>
                                            <tr class="text-[10px] font-bold uppercase text-glamora-muted">
                                                <th class="py-2 text-left">Nivel Cliente</th>
                                                <th class="py-2 text-center">Rango Unidades</th>
                                                <th class="py-2 text-right">Precio Unitario</th>
                                                <th class="py-2 text-right">% Desc.</th>
                                                <th class="py-2 text-right">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-glamora-blush/20">
                                            @foreach ($variant->tieredPrices as $tp)
                                                <tr>
                                                    <td class="py-2 font-semibold text-glamora-charcoal">{{ $tp->customer_tier->label() }}</td>
                                                    <td class="py-2 text-center font-mono">
                                                        {{ $tp->min_quantity }} &ndash; {{ $tp->max_quantity ?? '∞' }}
                                                    </td>
                                                    <td class="py-2 text-right font-bold text-glamora-charcoal">S/ {{ number_format((float) $tp->unit_price, 2) }}</td>
                                                    <td class="py-2 text-right font-semibold text-emerald-700">-{{ number_format((float) $tp->discount_percent, 1) }}%</td>
                                                    <td class="py-2 text-right">
                                                        @can('catalog.edit')
                                                            <form action="{{ route('admin.pim.products.tiered-prices.destroy', [$product, $variant, $tp]) }}" method="POST" class="inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-[11px] font-semibold text-rose-700 hover:underline">Quitar</button>
                                                            </form>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            @can('catalog.edit')
                                <form action="{{ route('admin.pim.products.tiered-prices.store', [$product, $variant]) }}" method="POST" class="mt-3 grid gap-2.5 border-t border-glamora-blush/30 pt-3 sm:grid-cols-5">
                                    @csrf
                                    <select name="customer_tier" required class="input-glamora py-1.5 text-xs">
                                        @foreach ($customerTiers as $tier)
                                            <option value="{{ $tier->value }}">{{ $tier->label() }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="min_quantity" min="1" placeholder="Cant. mín." required class="input-glamora py-1.5 text-xs">
                                    <input type="number" name="max_quantity" min="1" placeholder="Cant. máx. (opc)" class="input-glamora py-1.5 text-xs">
                                    <input type="number" step="0.01" min="0.10" name="unit_price" placeholder="Precio S/" required class="input-glamora py-1.5 text-xs">
                                    <button type="submit" class="rounded-xl bg-glamora-terracotta px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#b5695e]">
                                        + Guardar Escala
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Add New Variant Card --}}
            @can('catalog.edit')
                <div
                    x-data="{
                        newHex: '#C87D72',
                        newVarPreview: '',
                        onNewVarFile(e) {
                            const f = e.target.files[0];
                            if (f) {
                                this.newVarPreview = URL.createObjectURL(f);
                            }
                        }
                    }"
                    class="card-glamora p-6"
                >
                    <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                        + Añadir Nueva Variante / Tono
                    </h3>
                    <form action="{{ route('admin.pim.products.variants.store', $product) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">SKU *</label>
                                <input type="text" name="sku" required placeholder="GLM-SKU-NEW" class="input-glamora font-mono text-xs uppercase">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Nombre del Tono *</label>
                                <input type="text" name="shade_name" required placeholder="Rosa Andino" class="input-glamora text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Color Hexadecimal</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="newHex" class="h-9 w-11 cursor-pointer rounded-lg border border-glamora-charcoal/15 bg-white p-0.5">
                                    <input type="text" name="hex_color" x-model="newHex" pattern="^#[0-9A-Fa-f]{6}$" class="input-glamora font-mono text-xs uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Barcode</label>
                                <input type="text" name="barcode" class="input-glamora font-mono text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Gramaje (g)</label>
                                <input type="number" step="0.01" min="0" name="weight_grams" class="input-glamora text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Volumen (ml)</label>
                                <input type="number" step="0.01" min="0" name="volume_ml" class="input-glamora text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Precio Lista (S/) *</label>
                                <input type="number" step="0.01" min="0.10" name="base_price" required class="input-glamora text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Precio B2B Base / Costo *</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="number" step="0.01" min="0.10" name="wholesale_base_price" placeholder="B2B" required class="input-glamora text-xs">
                                    <input type="number" step="0.01" min="0" name="cost_price" value="0.00" placeholder="Costo" required class="input-glamora text-xs">
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 rounded-xl border border-glamora-blush/35 bg-glamora-cream/35 p-3 sm:grid-cols-2">
                            <div class="flex items-center gap-3">
                                <template x-if="newVarPreview">
                                    <img :src="newVarPreview" alt="Preview nueva variante" class="h-12 w-12 rounded-xl border border-glamora-blush/40 bg-white object-cover">
                                </template>
                                <div class="flex-1">
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">
                                        Fotografía del Tono (JPG, PNG, WEBP — Máx. 4 MB)
                                    </label>
                                    <input
                                        type="file"
                                        name="image_file"
                                        accept="image/jpeg,image/png,image/webp"
                                        @change="onNewVarFile($event)"
                                        class="input-glamora bg-white text-xs"
                                    >
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">
                                    O URL Fotográfica Existente
                                </label>
                                <input type="text" name="image_path" placeholder="https://images.unsplash.com/photo-..." class="input-glamora bg-white font-mono text-xs">
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                            <div class="flex gap-4 text-xs">
                                <label class="inline-flex items-center gap-2 font-medium">
                                    <input type="checkbox" name="is_default" value="1" class="h-4 w-4 accent-glamora-terracotta">
                                    Marcar como Default
                                </label>
                                <label class="inline-flex items-center gap-2 font-medium">
                                    <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 accent-glamora-terracotta">
                                    Activa
                                </label>
                            </div>

                            <button type="submit" class="btn-glamora-terracotta px-6 py-2 text-xs">
                                + Agregar Variante
                            </button>
                        </div>
                    </form>
                </div>
            @endcan
        </section>
    </div>
@endsection
