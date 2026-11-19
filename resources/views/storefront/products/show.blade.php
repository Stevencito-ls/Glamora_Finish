@extends('layouts.storefront')

@section('title', $product->meta_title ?: "{$product->name} | Glamora Perú")
@section('meta_description', $product->meta_description ?: ($product->short_description ?: $product->name))
@section('canonical_url', route('product.show', $product->slug))
@section('og_type', 'product')
@section('og_image', $galleryImages[0] ?? $product->image_url)

@push('structured_data')
    @php
        $schemaDefaultVariant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
        $schemaOfferPrice = $schemaDefaultVariant
            ? number_format((float) $schemaDefaultVariant->priceForTier($activeTier, 1), 2, '.', '')
            : '0.00';
        $schemaTotalStock = $product->variants->sum(fn ($v): int => $v->availableStock());
        $schemaImages = $galleryImages !== []
            ? $galleryImages
            : [$product->image_url];

        $productSchema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->meta_description ?: ($product->short_description ?: $product->description),
            'sku' => $schemaDefaultVariant?->sku,
            'image' => $schemaImages,
            'brand' => $product->brand ? [
                '@type' => 'Brand',
                'name' => $product->brand->name,
            ] : null,
            'category' => $product->category?->name,
            'offers' => $schemaDefaultVariant ? [
                '@type' => 'Offer',
                'url' => route('product.show', $product->slug),
                'priceCurrency' => 'PEN',
                'price' => $schemaOfferPrice,
                'availability' => $schemaTotalStock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => 'Glamora Perú',
                ],
            ] : null,
        ], static fn (mixed $value): bool => $value !== null);

        $productBreadcrumbs = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Inicio',
                'item' => route('home'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Catálogo',
                'item' => route('catalog.index'),
            ],
        ];

        if ($product->category) {
            if ($product->category->parent) {
                $productBreadcrumbs[] = [
                    '@type' => 'ListItem',
                    'position' => count($productBreadcrumbs) + 1,
                    'name' => $product->category->parent->name,
                    'item' => route('catalog.category', $product->category->parent->slug),
                ];
            }

            $productBreadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => count($productBreadcrumbs) + 1,
                'name' => $product->category->name,
                'item' => route('catalog.category', $product->category->slug),
            ];
        }

        $productBreadcrumbs[] = [
            '@type' => 'ListItem',
            'position' => count($productBreadcrumbs) + 1,
            'name' => $product->name,
            'item' => route('product.show', $product->slug),
        ];

        $productBreadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $productBreadcrumbs,
        ];
    @endphp
    <script type="application/ld+json">@json($productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
    <script type="application/ld+json">@json($productBreadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
@endpush

@section('content')
    <div
        x-data="{
            variants: @js($variantsData),
            gallery: @js($galleryImages),
            selectedIndex: 0,
            customGalleryImage: null,
            quantity: 1,
            activeTab: 'description',
            activeTierValue: '{{ $activeTier->value }}',
            get current() {
                return this.variants[this.selectedIndex] ?? this.variants[0];
            },
            get displayedImage() {
                if (this.customGalleryImage) return this.customGalleryImage;
                return this.current ? this.current.image_url : (this.gallery[0] ?? '');
            },
            selectVariant(idx) {
                this.selectedIndex = idx;
                this.customGalleryImage = null;
                if (this.current && this.current.available_stock > 0 && this.quantity > this.current.available_stock) {
                    this.quantity = this.current.available_stock;
                } else if (this.quantity < 1) {
                    this.quantity = 1;
                }
            },
            effectiveUnitPrice() {
                if (!this.current) return 0;
                const qty = Math.max(1, Number(this.quantity) || 1);
                const matched = (this.current.tiered_prices ?? []).filter(r =>
                    r.tier === this.activeTierValue &&
                    qty >= Number(r.min_quantity ?? 1) &&
                    (r.max_quantity === null || r.max_quantity === undefined || qty <= Number(r.max_quantity))
                );
                if (matched.length > 0) {
                    return Number(matched[matched.length - 1].unit_price);
                }
                if (this.activeTierValue === 'minorista' && qty >= 3) {
                    return Number(this.current.minorista_volume_price);
                }
                return Number(this.current.base_price);
            }
        }"
        class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8"
    >
        {{-- Breadcrumb --}}
        <nav aria-label="Breadcrumb" class="mb-6 flex flex-wrap items-center gap-2 text-xs text-glamora-muted">
            <a href="{{ route('home') }}" class="hover:text-glamora-terracotta">Inicio</a>
            <span>/</span>
            <a href="{{ route('catalog.index') }}" class="hover:text-glamora-terracotta">Catálogo</a>
            @if ($product->category)
                @if ($product->category->parent)
                    <span>/</span>
                    <a href="{{ route('catalog.category', $product->category->parent->slug) }}" class="hover:text-glamora-terracotta">
                        {{ $product->category->parent->name }}
                    </a>
                @endif
                <span>/</span>
                <a href="{{ route('catalog.category', $product->category->slug) }}" class="hover:text-glamora-terracotta">
                    {{ $product->category->name }}
                </a>
            @endif
            <span>/</span>
            <span class="font-semibold text-glamora-charcoal">{{ $product->name }}</span>
        </nav>

        {{-- Main Product Grid --}}
        <div class="grid gap-10 lg:grid-cols-12">
            {{-- Left Column: Gallery --}}
            <div class="lg:col-span-6">
                <div class="card-glamora overflow-hidden p-3">
                    <div class="relative aspect-square overflow-hidden rounded-2xl bg-glamora-cream">
                        <img
                            :src="displayedImage"
                            alt="{{ $product->name }}"
                            class="h-full w-full object-cover transition duration-300"
                        >
                        @if ($product->is_b2b_kit)
                            <span class="absolute top-4 left-4 rounded-full bg-glamora-charcoal px-3 py-1 text-xs font-bold uppercase tracking-wider text-glamora-gold">
                                Kit Profesional B2B
                            </span>
                        @endif
                    </div>

                    @if (count($galleryImages) > 0)
                        <div class="mt-3 flex flex-wrap gap-2.5">
                            <template x-if="current">
                                <button
                                    type="button"
                                    @click="customGalleryImage = null"
                                    :class="!customGalleryImage ? 'ring-2 ring-glamora-terracotta' : 'opacity-75 hover:opacity-100'"
                                    class="h-16 w-16 overflow-hidden rounded-xl border border-glamora-blush/35 bg-glamora-cream transition"
                                >
                                    <img :src="current.image_url" alt="Tono seleccionado" class="h-full w-full object-cover">
                                </button>
                            </template>

                            <template x-for="(imgUrl, gIdx) in gallery" :key="'gal-' + gIdx">
                                <button
                                    type="button"
                                    @click="customGalleryImage = imgUrl"
                                    :class="customGalleryImage === imgUrl ? 'ring-2 ring-glamora-terracotta' : 'opacity-75 hover:opacity-100'"
                                    class="h-16 w-16 overflow-hidden rounded-xl border border-glamora-blush/35 bg-glamora-cream transition"
                                >
                                    <img :src="imgUrl" alt="{{ $product->name }}" class="h-full w-full object-cover">
                                </button>
                            </template>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right Column: Details, Hex Swatches, FEFO Batch & Tiered Pricing --}}
            <div class="space-y-6 lg:col-span-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        @if ($product->brand)
                            <a
                                href="{{ route('catalog.index', ['marca' => $product->brand->slug]) }}"
                                class="text-xs font-bold uppercase tracking-[0.18em] text-glamora-terracotta hover:underline"
                            >
                                {{ $product->brand->name }}
                            </a>
                        @endif
                        @if ($product->sanitary_registration)
                            <span class="badge-glamora-gold">
                                DIGEMID · {{ $product->sanitary_registration }}
                            </span>
                        @endif
                    </div>

                    <h1 class="mt-2 font-serif text-3xl font-semibold leading-tight text-glamora-charcoal sm:text-4xl">
                        {{ $product->name }}
                    </h1>

                    <p class="mt-3 text-sm leading-relaxed text-glamora-muted">
                        {{ $product->short_description }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        @if ($product->skin_type)
                            <span class="rounded-full border border-glamora-blush/40 bg-white px-3 py-1 text-glamora-charcoal">
                                <strong>Piel / Perfil:</strong> {{ $product->skin_type }}
                            </span>
                        @endif
                        @if ($product->finish_type)
                            <span class="rounded-full border border-glamora-blush/40 bg-white px-3 py-1 text-glamora-charcoal">
                                <strong>Acabado:</strong> {{ $product->finish_type }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Price & Stock Header --}}
                <div class="card-glamora p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-4">
                        <div>
                            <span class="block text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">
                                Precio Unitario ({{ $activeTier->label() }})
                            </span>
                            <div class="mt-1 flex items-baseline gap-2.5">
                                <span
                                    class="font-serif text-4xl font-bold text-glamora-charcoal"
                                    x-text="'S/ ' + effectiveUnitPrice().toFixed(2)"
                                ></span>
                                <template x-if="current && effectiveUnitPrice() < current.base_price">
                                    <span class="text-sm text-glamora-muted line-through" x-text="'S/ ' + Number(current.base_price).toFixed(2)"></span>
                                </template>
                            </div>
                        </div>

                        <div class="text-right">
                            <template x-if="current && current.available_stock > 0">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    <span x-text="'Stock disponible: ' + current.available_stock + ' uds.'"></span>
                                </span>
                            </template>
                            <template x-if="current && current.available_stock <= 0">
                                <span class="inline-flex rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">
                                    Agotado temporalmente
                                </span>
                            </template>
                            <p class="mt-1.5 font-mono text-xs text-glamora-muted" x-text="current ? 'SKU: ' + current.sku + ' · ' + current.measure : ''"></p>
                        </div>
                    </div>

                    {{-- Visual Circular Hex Shade Selector --}}
                    <div class="mt-5 border-t border-glamora-blush/25 pt-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-glamora-charcoal">
                                Tono / Presentación:
                                <span class="font-semibold text-glamora-terracotta" x-text="current ? current.shade_name : ''"></span>
                            </span>
                            <span class="text-[11px] font-mono text-glamora-muted" x-text="current ? current.hex_color : ''"></span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-3">
                            <template x-for="(variant, idx) in variants" :key="variant.id">
                                <button
                                    type="button"
                                    @click="selectVariant(idx)"
                                    :class="selectedIndex === idx ? 'swatch-ring-active' : ''"
                                    class="swatch-ring h-9 w-9"
                                    :style="'background-color: ' + variant.hex_color"
                                    :title="variant.shade_name + ' (' + variant.sku + ')'"
                                    :aria-label="'Seleccionar tono ' + variant.shade_name"
                                ></button>
                            </template>
                        </div>
                    </div>

                    {{-- FEFO Next Batch Info --}}
                    <template x-if="current && current.next_fefo_batch">
                        <div class="mt-5 rounded-xl border border-glamora-gold/35 bg-glamora-cream/85 px-4 py-3 text-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-bold uppercase tracking-wider text-glamora-charcoal">
                                    Garantía de Frescura y Lote Vigente
                                </span>
                                <span class="font-mono font-semibold text-glamora-terracotta" x-text="current.next_fefo_batch.batch_number"></span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-glamora-muted">
                                <span>
                                    Vencimiento: <strong class="text-glamora-charcoal" x-text="current.next_fefo_batch.expiration_date"></strong>
                                    (<span x-text="current.next_fefo_batch.available_qty + ' uds. en lote'"></span>)
                                </span>
                                <span x-show="current.next_fefo_batch.warehouse_code" x-text="'Ubicación: ' + current.next_fefo_batch.warehouse_code"></span>
                            </div>
                        </div>
                    </template>

                    {{-- Real Add to Cart Form --}}
                    <form action="{{ route('cart.add') }}" method="POST" class="mt-5 border-t border-glamora-blush/25 pt-5">
                        @csrf
                        <input type="hidden" name="variant_id" :value="current ? current.id : ''">

                        <div class="flex flex-wrap items-center gap-3">
                            <div class="inline-flex items-center rounded-full border border-glamora-charcoal/20 bg-glamora-cream px-2 py-1">
                                <button
                                    type="button"
                                    @click="quantity = Math.max(1, (Number(quantity) || 1) - 1)"
                                    aria-label="Restar unidad"
                                    class="h-8 w-8 rounded-full text-base font-bold text-glamora-charcoal hover:bg-white"
                                >
                                    &minus;
                                </button>
                                <input
                                    type="number"
                                    name="quantity"
                                    x-model.number="quantity"
                                    min="1"
                                    :max="current ? Math.max(1, current.available_stock) : 1"
                                    class="w-14 bg-transparent text-center text-sm font-bold text-glamora-charcoal focus:outline-none"
                                >
                                <button
                                    type="button"
                                    @click="quantity = current ? Math.min(Math.max(1, current.available_stock), (Number(quantity) || 1) + 1) : (Number(quantity) || 1) + 1"
                                    aria-label="Sumar unidad"
                                    class="h-8 w-8 rounded-full text-base font-bold text-glamora-charcoal hover:bg-white"
                                >
                                    +
                                </button>
                            </div>

                            <button
                                type="submit"
                                :disabled="!current || current.available_stock <= 0"
                                class="btn-glamora-primary flex-1 py-3.5 text-xs uppercase tracking-wider"
                            >
                                <span>Agregar a la Bolsa</span>
                                <span x-text="'· S/ ' + (effectiveUnitPrice() * Math.max(1, Number(quantity) || 1)).toFixed(2)"></span>
                            </button>
                        </div>

                        <p x-show="activeTierValue === 'minorista' && quantity < 3" class="mt-2.5 text-[11px] text-glamora-muted">
                            Tip de ahorro: llevando 3 o más unidades accedes automáticamente al 5% de descuento por volumen minorista.
                        </p>
                    </form>
                </div>

                {{-- Tiered Pricing Full Table --}}
                <div class="card-glamora overflow-hidden">
                    <div class="flex items-center justify-between border-b border-glamora-blush/30 bg-glamora-cream/75 px-5 py-3.5">
                        <div>
                            <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                Precios Escalonados por Volumen &amp; B2B
                            </h2>
                            <p class="text-[11px] text-glamora-muted">
                                Valores aplicables al tono <strong x-text="current ? current.shade_name : ''"></strong> (incl. IGV 18%)
                            </p>
                        </div>
                        <a href="{{ route('wholesale.index') }}" class="text-xs font-semibold text-glamora-terracotta hover:underline">
                            Solicitar B2B &rarr;
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-glamora-blush/25 text-left text-xs">
                            <thead class="bg-white text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                                <tr>
                                    <th class="px-5 py-3">Nivel Cliente</th>
                                    <th class="px-5 py-3">Rango</th>
                                    <th class="px-5 py-3">Descuento</th>
                                    <th class="px-5 py-3 text-right">Precio Unitario</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-glamora-blush/20 bg-white">
                                <template x-for="(row, rIdx) in (current ? current.tiered_prices : [])" :key="rIdx">
                                    <tr :class="row.tier === activeTierValue ? 'bg-glamora-cream/70 font-semibold' : ''">
                                        <td class="px-5 py-2.5 text-glamora-charcoal" x-text="row.tier_label"></td>
                                        <td class="px-5 py-2.5 text-glamora-muted" x-text="row.range_label"></td>
                                        <td class="px-5 py-2.5">
                                            <span
                                                x-show="row.discount_percent > 0"
                                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
                                                x-text="'-' + row.discount_percent + '%'"
                                            ></span>
                                            <span x-show="row.discount_percent <= 0" class="text-glamora-muted">Precio lista</span>
                                        </td>
                                        <td class="px-5 py-2.5 text-right font-bold text-glamora-charcoal" x-text="'S/ ' + Number(row.unit_price).toFixed(2)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Editorial Tabs: Description / Ingredients / How to use / DIGEMID --}}
        <section class="mt-14 card-glamora p-6 sm:p-8">
            <div class="flex flex-wrap gap-2 border-b border-glamora-blush/35 pb-4">
                <button
                    type="button"
                    @click="activeTab = 'description'"
                    :class="activeTab === 'description' ? 'bg-glamora-charcoal text-glamora-cream' : 'bg-glamora-cream text-glamora-charcoal hover:bg-glamora-blush/25'"
                    class="rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-wider transition"
                >
                    Descripción
                </button>
                <button
                    type="button"
                    @click="activeTab = 'ingredients'"
                    :class="activeTab === 'ingredients' ? 'bg-glamora-charcoal text-glamora-cream' : 'bg-glamora-cream text-glamora-charcoal hover:bg-glamora-blush/25'"
                    class="rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-wider transition"
                >
                    Ingredientes INCI
                </button>
                <button
                    type="button"
                    @click="activeTab = 'how_to_use'"
                    :class="activeTab === 'how_to_use' ? 'bg-glamora-charcoal text-glamora-cream' : 'bg-glamora-cream text-glamora-charcoal hover:bg-glamora-blush/25'"
                    class="rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-wider transition"
                >
                    Modo de Uso
                </button>
                <button
                    type="button"
                    @click="activeTab = 'digemid'"
                    :class="activeTab === 'digemid' ? 'bg-glamora-charcoal text-glamora-cream' : 'bg-glamora-cream text-glamora-charcoal hover:bg-glamora-blush/25'"
                    class="rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-wider transition"
                >
                    Registro Sanitario &amp; Garantía
                </button>
            </div>

            <div class="pt-6 text-sm leading-relaxed text-glamora-charcoal/85">
                <div x-show="activeTab === 'description'">
                    <p>{{ $product->description }}</p>
                </div>
                <div x-show="activeTab === 'ingredients'" x-cloak>
                    <p class="font-mono text-xs leading-relaxed text-glamora-charcoal">
                        {{ $product->ingredients ?: 'Consultar empaque original para listado completo INCI.' }}
                    </p>
                </div>
                <div x-show="activeTab === 'how_to_use'" x-cloak>
                    <p>{{ $product->how_to_use ?: 'Seguir indicaciones del especialista o asesoría Glamora.' }}</p>
                </div>
                <div x-show="activeTab === 'digemid'" x-cloak class="space-y-2">
                    <p>
                        <strong>Notificación Sanitaria Obligatoria (DIGEMID):</strong>
                        {{ $product->sanitary_registration ?: 'En trámite / verificado' }}.
                    </p>
                    <p>
                        Cada pedido es preparado en almacén climatizado bajo política <strong>FEFO (First Expired, First Out)</strong>, asegurando que el lote despachado cuente con vida útil vigente verificada.
                    </p>
                </div>
            </div>
        </section>

        {{-- Related Products --}}
        @if ($relatedProducts->isNotEmpty())
            <section class="mt-16">
                <div class="mb-6 flex items-end justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">Completa tu rutina</span>
                        <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal">
                            Productos relacionados
                        </h2>
                    </div>
                    <a href="{{ route('catalog.index') }}" class="text-xs font-semibold uppercase tracking-wider text-glamora-charcoal hover:text-glamora-terracotta">
                        Ver más &rarr;
                    </a>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($relatedProducts as $related)
                        @include('storefront.partials.product-card', [
                            'product' => $related,
                            'pricingTier' => $activeTier,
                        ])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
