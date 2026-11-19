@extends('layouts.admin')

@section('title', 'Crear Producto PIM — Glamora Perú')
@section('page_title', 'Registrar Nuevo Producto en PIM')
@section('page_subtitle', 'Completa la ficha técnica sanitaria DIGEMID, sube fotografías reales y configura su variante inicial.')

@section('header_actions')
    <a href="{{ route('admin.pim.products.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Volver al Catálogo
    </a>
@endsection

@section('content')
    <form
        action="{{ route('admin.pim.products.store') }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="{
            mainPreview: '',
            galleryPreviews: [],
            variantPreview: '',
            onMainFile(e) {
                const file = e.target.files[0];
                if (file) {
                    this.mainPreview = URL.createObjectURL(file);
                }
            },
            onGalleryFiles(e) {
                this.galleryPreviews = Array.from(e.target.files || []).map(f => URL.createObjectURL(f));
            },
            onVariantFile(e) {
                const file = e.target.files[0];
                if (file) {
                    this.variantPreview = URL.createObjectURL(file);
                }
            }
        }"
        class="mx-auto max-w-5xl space-y-6"
    >
        @csrf

        {{-- Product Info --}}
        <section class="card-glamora space-y-5 p-6">
            <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                1. Información Editorial &amp; Regulación DIGEMID
            </h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="prod_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Nombre del Producto *</label>
                    <input id="prod_name" type="text" name="name" value="{{ old('name') }}" required class="input-glamora">
                </div>
                <div>
                    <label for="prod_slug" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Slug URL (opcional)</label>
                    <input id="prod_slug" type="text" name="slug" value="{{ old('slug') }}" class="input-glamora font-mono">
                </div>

                <div>
                    <label for="prod_category" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Categoría *</label>
                    <select id="prod_category" name="category_id" required class="input-glamora">
                        <option value="">Selecciona categoría...</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('category_id') === (string) $cat->id)>
                                {{ $cat->parent ? $cat->parent->name . ' › ' : '' }}{{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="prod_brand" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Marca *</label>
                    <select id="prod_brand" name="brand_id" required class="input-glamora">
                        <option value="">Selecciona marca...</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected((string) old('brand_id') === (string) $brand->id)>
                                {{ $brand->name }} ({{ $brand->country_origin }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="prod_nsoc" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Registro Sanitario DIGEMID (NSOC) *</label>
                    <input id="prod_nsoc" type="text" name="sanitary_registration" value="{{ old('sanitary_registration') }}" placeholder="NSOC54321-26PE" required class="input-glamora font-mono uppercase">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="prod_skin" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Tipo de Piel</label>
                        <input id="prod_skin" type="text" name="skin_type" value="{{ old('skin_type') }}" placeholder="Mixta / Todo tipo" class="input-glamora">
                    </div>
                    <div>
                        <label for="prod_finish" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Acabado</label>
                        <input id="prod_finish" type="text" name="finish_type" value="{{ old('finish_type') }}" placeholder="Velvet Matte" class="input-glamora">
                    </div>
                </div>
            </div>

            <div>
                <label for="prod_short" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Resumen Corto</label>
                <input id="prod_short" type="text" name="short_description" value="{{ old('short_description') }}" maxlength="300" class="input-glamora">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="prod_desc" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Descripción Completa</label>
                    <textarea id="prod_desc" name="description" rows="4" class="input-glamora">{{ old('description') }}</textarea>
                </div>
                <div class="space-y-4">
                    <div>
                        <label for="prod_ingredients" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Ingredientes (INCI)</label>
                        <textarea id="prod_ingredients" name="ingredients" rows="2" class="input-glamora">{{ old('ingredients') }}</textarea>
                    </div>
                    <div>
                        <label for="prod_how" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Modo de Uso</label>
                        <textarea id="prod_how" name="how_to_use" rows="2" class="input-glamora">{{ old('how_to_use') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="prod_min" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Stock Mínimo Alerta *</label>
                    <input id="prod_min" type="number" min="0" name="min_stock_alert" value="{{ old('min_stock_alert', 10) }}" required class="input-glamora">
                </div>
                <div>
                    <label for="prod_reorder" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Punto de Reorden *</label>
                    <input id="prod_reorder" type="number" min="0" name="reorder_point" value="{{ old('reorder_point', 25) }}" required class="input-glamora">
                </div>
            </div>

            {{-- Fotografía Principal + Galería con Preview Alpine --}}
            <div class="grid gap-5 border-t border-glamora-blush/30 pt-5 lg:grid-cols-2">
                <div class="space-y-3 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                    <label for="prod_img_file" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Fotografía Principal (JPG, PNG, WEBP — Máx. 4 MB)
                    </label>
                    <template x-if="mainPreview">
                        <img :src="mainPreview" alt="Previsualización principal" class="h-36 w-36 rounded-xl border border-glamora-blush/40 bg-white object-cover">
                    </template>
                    <input
                        id="prod_img_file"
                        type="file"
                        name="main_image_file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onMainFile($event)"
                        class="input-glamora bg-white text-xs"
                    >
                    <div>
                        <label for="prod_img_path" class="mb-1 block text-[11px] font-semibold text-glamora-muted">O URL Fotográfica</label>
                        <input id="prod_img_path" type="text" name="main_image" value="{{ old('main_image') }}" placeholder="https://images.unsplash.com/photo-..." class="input-glamora bg-white font-mono text-xs">
                    </div>
                </div>

                <div class="space-y-3 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                    <label for="prod_gallery_files" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Galería del Producto (Subida Múltiple — Máx. 4 MB c/u)
                    </label>
                    <template x-if="galleryPreviews.length > 0">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="(src, idx) in galleryPreviews" :key="idx">
                                <img :src="src" alt="Preview galería" class="h-20 w-20 rounded-xl border border-glamora-blush/40 bg-white object-cover">
                            </template>
                        </div>
                    </template>
                    <input
                        id="prod_gallery_files"
                        type="file"
                        name="gallery_files[]"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        @change="onGalleryFiles($event)"
                        class="input-glamora bg-white text-xs"
                    >
                    <p class="text-[11px] text-glamora-muted">
                        Puedes seleccionar varias fotografías (producto, textura cosmética y aplicación editorial).
                    </p>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="prod_meta_title" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Meta Title SEO</label>
                    <input id="prod_meta_title" type="text" name="meta_title" value="{{ old('meta_title') }}" class="input-glamora">
                </div>
                <div>
                    <label for="prod_meta_desc" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Meta Description SEO</label>
                    <input id="prod_meta_desc" type="text" name="meta_description" value="{{ old('meta_description') }}" class="input-glamora">
                </div>
            </div>

            <div class="flex flex-wrap gap-5 border-t border-glamora-blush/30 pt-4 text-sm">
                <label class="inline-flex items-center gap-2 font-medium">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="h-4 w-4 accent-glamora-terracotta">
                    Producto Activo
                </label>
                <label class="inline-flex items-center gap-2 font-medium">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured')) class="h-4 w-4 accent-glamora-terracotta">
                    Destacado
                </label>
                <label class="inline-flex items-center gap-2 font-medium">
                    <input type="checkbox" name="is_new_launch" value="1" @checked(old('is_new_launch')) class="h-4 w-4 accent-glamora-terracotta">
                    Nuevo Lanzamiento
                </label>
                <label class="inline-flex items-center gap-2 font-medium">
                    <input type="checkbox" name="is_b2b_kit" value="1" @checked(old('is_b2b_kit')) class="h-4 w-4 accent-glamora-terracotta">
                    Kit / Pack B2B
                </label>
            </div>
        </section>

        {{-- Initial Default Variant --}}
        <section x-data="{ hex: @js(old('variant_hex_color', '#C87D72')) }" class="card-glamora space-y-5 p-6">
            <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                2. Variante Inicial Predeterminada (SKU, Colorimetría &amp; Fotografía)
            </h2>

            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="var_sku" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">SKU Único *</label>
                    <input id="var_sku" type="text" name="variant_sku" value="{{ old('variant_sku') }}" placeholder="GLM-NEW-001" required class="input-glamora font-mono uppercase">
                </div>
                <div>
                    <label for="var_shade" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Nombre del Tono / Presentación *</label>
                    <input id="var_shade" type="text" name="variant_shade_name" value="{{ old('variant_shade_name') }}" placeholder="Terracota Limeña" required class="input-glamora">
                </div>
                <div>
                    <label for="var_hex" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Tono Hexadecimal</label>
                    <div class="flex items-center gap-2.5">
                        <input type="color" x-model="hex" aria-label="Selector visual de tono" class="h-10 w-12 cursor-pointer rounded-lg border border-glamora-charcoal/15 bg-white p-1">
                        <input id="var_hex" type="text" name="variant_hex_color" x-model="hex" pattern="^#[0-9A-Fa-f]{6}$" class="input-glamora font-mono uppercase">
                    </div>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-3 lg:grid-cols-6">
                <div>
                    <label for="var_g" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Gramaje (g)</label>
                    <input id="var_g" type="number" step="0.01" min="0" name="variant_weight_grams" value="{{ old('variant_weight_grams') }}" class="input-glamora">
                </div>
                <div>
                    <label for="var_ml" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Volumen (ml)</label>
                    <input id="var_ml" type="number" step="0.01" min="0" name="variant_volume_ml" value="{{ old('variant_volume_ml') }}" class="input-glamora">
                </div>
                <div>
                    <label for="var_base" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Precio Minorista (S/) *</label>
                    <input id="var_base" type="number" step="0.01" min="0.10" name="variant_base_price" value="{{ old('variant_base_price') }}" required class="input-glamora">
                </div>
                <div>
                    <label for="var_b2b" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Precio Base B2B (S/) *</label>
                    <input id="var_b2b" type="number" step="0.01" min="0.10" name="variant_wholesale_base_price" value="{{ old('variant_wholesale_base_price') }}" required class="input-glamora">
                </div>
                <div>
                    <label for="var_cost" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Costo Unitario (S/) *</label>
                    <input id="var_cost" type="number" step="0.01" min="0" name="variant_cost_price" value="{{ old('variant_cost_price', '0.00') }}" required class="input-glamora">
                </div>
                <div>
                    <label for="var_barcode" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Código de Barras</label>
                    <input id="var_barcode" type="text" name="variant_barcode" value="{{ old('variant_barcode') }}" class="input-glamora font-mono">
                </div>
            </div>

            <div class="rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                <label for="var_img_file" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Fotografía Específica del Tono / Variante (Opcional — si se omite usa la foto principal)
                </label>
                <div class="flex flex-wrap items-center gap-4">
                    <template x-if="variantPreview">
                        <img :src="variantPreview" alt="Preview variante" class="h-16 w-16 rounded-xl border border-glamora-blush/40 bg-white object-cover">
                    </template>
                    <input
                        id="var_img_file"
                        type="file"
                        name="variant_image_file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onVariantFile($event)"
                        class="input-glamora max-w-md bg-white text-xs"
                    >
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="btn-glamora-primary px-8">
                Crear Producto &amp; Variante Inicial
            </button>
        </div>
    </form>
@endsection
