@extends('layouts.storefront')

@php
    $heroSlides = isset($heroBanners) && $heroBanners->isNotEmpty()
        ? $heroBanners->map(fn (\App\Models\Banner $b): array => [
            'eyebrow' => $b->eyebrow ?: 'Colección Editorial 2026',
            'title' => $b->title,
            'subtitle' => $b->subtitle ?: '',
            'ctaLabel' => $b->button_text ?: 'Explorar Catálogo',
            'ctaUrl' => $b->safeButtonUrl() ?: route('catalog.index'),
            'secondaryLabel' => $b->secondary_button_text ?: 'Ver Kits B2B',
            'secondaryUrl' => $b->safeSecondaryButtonUrl() ?: route('catalog.index', ['b2b' => 1]),
            'image' => $b->image_url,
            'accentImage' => $b->accent_image_url,
            'tag' => $b->badge_text ?: 'NSOC Verificado · Cruelty-Free',
        ])->values()->all()
        : [
            [
                'eyebrow' => 'Colección Editorial 2026',
                'title' => 'Alta cosmética botánica diseñada para el clima y subtono latino.',
                'subtitle' => 'Pigmentos aterciopelados de larga duración y activos amazónicos con registro sanitario DIGEMID y trazabilidad FEFO en cada lote.',
                'ctaLabel' => 'Explorar Catálogo',
                'ctaUrl' => route('catalog.index'),
                'secondaryLabel' => 'Ver Kits B2B',
                'secondaryUrl' => route('catalog.index', ['b2b' => 1]),
                'image' => \App\Support\CosmeticImageResolver::resolve(null, 'product', 'labial-liquido-velvet-matte'),
                'accentImage' => \App\Support\CosmeticImageResolver::resolve(null, 'product', 'serum-vitamina-c-camu-camu'),
                'tag' => 'NSOC Verificado · Cruelty-Free',
            ],
            [
                'eyebrow' => 'Alianzas Comerciales B2B',
                'title' => 'Abastece tu salón, spa o boutique con hasta 30% de descuento mayorista.',
                'subtitle' => 'Escalas Bronce, Plata y Oro con exhibidores llave en mano, packs de cabina profesional y línea de crédito aprobada hasta a 30 días.',
                'ctaLabel' => 'Programa Mayoristas',
                'ctaUrl' => route('wholesale.index'),
                'secondaryLabel' => 'Calcular Margen',
                'secondaryUrl' => route('wholesale.index') . '#calculadora-b2b',
                'image' => \App\Support\CosmeticImageResolver::resolve(null, 'product', 'pack-cabina-facial-kora-derma-b2b'),
                'accentImage' => \App\Support\CosmeticImageResolver::resolve(null, 'product', 'exhibidor-labiales-velvet-matte-b2b'),
                'tag' => 'Crédito 30 días · Envíos a todo el Perú',
            ],
            [
                'eyebrow' => 'Journal Dermocosmético',
                'title' => 'Ciencia botánica y colorimetría aplicada para tu rutina diaria o cabina.',
                'subtitle' => 'Descubre cómo combinar Vitamina C de camu camu, ceramidas reparadoras y protección mineral FPS 50+ frente a la radiación andina.',
                'ctaLabel' => 'Leer Guías del Blog',
                'ctaUrl' => route('blog.index'),
                'secondaryLabel' => 'Rastrear Pedido',
                'secondaryUrl' => route('tracking.index'),
                'image' => \App\Support\CosmeticImageResolver::resolve(null, 'post', 'rutina-vitamina-c-humedad-lima'),
                'accentImage' => \App\Support\CosmeticImageResolver::resolve(null, 'product', 'fluido-solar-mineral-fps50'),
                'tag' => 'Protocolos & Shoppable Stories',
            ],
        ];
@endphp

@section('title', 'Glamora Perú — Alta Cosmética Botánica, Colorimetría & Distribución B2B')
@section('meta_description', 'Tienda boutique de maquillaje editorial, dermocosmética botánica y abastecimiento mayorista B2B en Perú con registro sanitario DIGEMID y trazabilidad FEFO.')
@section('canonical_url', route('home'))
@section('og_type', 'website')
@section('og_image', $heroSlides[0]['image'])

@push('structured_data')
    @php
        $schemaSettings = is_array($storeSettings ?? null) ? $storeSettings : [];
        $schemaStoreName = trim((string) ($schemaSettings['store_name'] ?? '')) ?: 'Glamora Perú';
        $schemaStoreEmail = trim((string) ($schemaSettings['store_email'] ?? '')) ?: 'info.glamora@gmail.com';
        $schemaStorePhone = trim((string) ($schemaSettings['store_phone'] ?? '')) ?: '+51 917 328 085';
        $schemaSameAs = array_values(array_filter([
            $schemaSettings['social_instagram'] ?? 'https://instagram.com/glamora.pe',
            $schemaSettings['social_tiktok'] ?? 'https://tiktok.com/@glamoracosmeticos',
            $schemaSettings['social_facebook'] ?? 'https://facebook.com/glamora.pe',
        ], static function (mixed $url): bool {
            if (! is_string($url)) {
                return false;
            }
            $trimmed = trim($url);
            if ($trimmed === '' || ! filter_var($trimmed, FILTER_VALIDATE_URL)) {
                return false;
            }

            return in_array(strtolower((string) parse_url($trimmed, PHP_URL_SCHEME)), ['http', 'https'], true);
        }));

        $organizationSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $schemaStoreName,
            'legalName' => 'Glamora Perú S.A.C.',
            'url' => route('home'),
            'logo' => \App\Support\CosmeticImageResolver::resolve(null, 'brand', 'aruma-atelier'),
            'email' => $schemaStoreEmail,
            'telephone' => $schemaStorePhone,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Lima',
                'addressCountry' => 'PE',
            ],
        ];

        if ($schemaSameAs !== []) {
            $organizationSchema['sameAs'] = $schemaSameAs;
        }

        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $schemaStoreName,
            'url' => route('home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('catalog.index') . '?buscar={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    @endphp
    <script type="application/ld+json">@json($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
    <script type="application/ld+json">@json($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
@endpush

@section('content')
    {{-- 1. HERO SLIDER EDITORIAL (Alpine.js + Modelo Banner) --}}
    <section
        x-data="{
            current: 0,
            paused: false,
            slides: @js($heroSlides),
            next() {
                this.current = (this.current + 1) % this.slides.length;
            },
            prev() {
                this.current = (this.current - 1 + this.slides.length) % this.slides.length;
            },
            init() {
                if (this.slides.length > 1) {
                    setInterval(() => {
                        if (!this.paused) this.next();
                    }, 6500);
                }
            }
        }"
        @mouseenter="paused = true"
        @mouseleave="paused = false"
        class="hero-glamora-surface relative overflow-hidden border-b border-glamora-blush/35"
    >
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
            <div class="grid items-center gap-10 lg:grid-cols-12">
                {{-- Left Copy --}}
                <div class="lg:col-span-6">
                    <span class="badge-glamora-gold" x-text="slides[current].eyebrow"></span>

                    <h1
                        class="mt-4 font-serif text-4xl font-semibold leading-[1.08] text-glamora-charcoal sm:text-5xl lg:text-[3.4rem]"
                        x-text="slides[current].title"
                    ></h1>

                    <p
                        class="mt-5 max-w-xl text-base leading-relaxed text-glamora-muted"
                        x-text="slides[current].subtitle"
                    ></p>

                    <div class="mt-8 flex flex-wrap items-center gap-3.5">
                        <a :href="slides[current].ctaUrl" class="btn-glamora-primary">
                            <span x-text="slides[current].ctaLabel"></span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                        <a :href="slides[current].secondaryUrl" class="btn-glamora-outline">
                            <span x-text="slides[current].secondaryLabel"></span>
                        </a>
                        @if ($catalogPdfEnabled ?? true)
                            <a href="{{ route('catalog.pdf') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full border border-glamora-gold/60 bg-glamora-gold/10 px-4 py-3 text-xs font-semibold text-glamora-charcoal transition hover:bg-glamora-gold/25">
                                <svg class="h-4 w-4 text-glamora-terracotta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0-3-3m3 3 3-3m2 8.25H7a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 7 3h5.586a1 1 0 0 1 .707.293l4.414 4.414a1 1 0 0 1 .293.707V19.5a2.25 2.25 0 0 1-2.25 2.25Z" />
                                </svg>
                                Descargar Catálogo PDF
                            </a>
                        @endif
                    </div>

                    {{-- Slider Controls --}}
                    <div class="mt-10 flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="prev()"
                                aria-label="Slide anterior"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-glamora-charcoal/20 bg-white/80 text-glamora-charcoal transition hover:bg-glamora-charcoal hover:text-white"
                            >
                                &larr;
                            </button>
                            <button
                                type="button"
                                @click="next()"
                                aria-label="Siguiente slide"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-glamora-charcoal/20 bg-white/80 text-glamora-charcoal transition hover:bg-glamora-charcoal hover:text-white"
                            >
                                &rarr;
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <template x-for="(slide, idx) in slides" :key="idx">
                                <button
                                    type="button"
                                    @click="current = idx"
                                    :aria-label="'Ir al slide ' + (idx + 1)"
                                    :class="current === idx ? 'w-8 bg-glamora-terracotta' : 'w-2.5 bg-glamora-charcoal/25 hover:bg-glamora-charcoal/50'"
                                    class="h-2.5 rounded-full transition-all duration-300"
                                ></button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Right Visual Stage --}}
                <div class="relative lg:col-span-6">
                    <div class="relative mx-auto max-w-lg">
                        <div class="overflow-hidden rounded-[2rem] border border-glamora-gold/40 bg-white p-3 shadow-[0_24px_60px_-20px_rgba(43,37,35,0.18)]">
                            <img
                                :src="slides[current].image"
                                :alt="slides[current].title"
                                class="aspect-square w-full rounded-[1.5rem] bg-glamora-cream object-cover transition duration-500"
                            >
                        </div>

                        {{-- Floating Secondary Accent Card --}}
                        <div class="mt-4 flex items-center gap-3.5 rounded-2xl border border-glamora-blush/50 bg-white/95 p-3 shadow-lg backdrop-blur-sm sm:absolute sm:-bottom-5 sm:-left-6 sm:mt-0 sm:w-72">
                            <img
                                :src="slides[current].accentImage"
                                alt="Detalle Glamora"
                                class="h-14 w-14 shrink-0 rounded-xl border border-glamora-blush/30 bg-glamora-cream object-cover"
                            >
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">
                                    Garantía Glamora
                                </p>
                                <p class="truncate text-xs font-semibold text-glamora-charcoal" x-text="slides[current].tag"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 2. BARRA DE CONFIANZA --}}
    <section class="border-b border-glamora-blush/30 bg-white">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
            <div class="flex items-start gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-glamora-cream text-glamora-terracotta">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-sans text-sm font-bold text-glamora-charcoal">Registro Sanitario DIGEMID</h2>
                    <p class="mt-0.5 text-xs text-glamora-muted">Código NSOC auditado en el 100% de nuestras fórmulas cosméticas.</p>
                </div>
            </div>

            <div class="flex items-start gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-glamora-cream text-glamora-terracotta">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-sans text-sm font-bold text-glamora-charcoal">Trazabilidad FEFO Real</h2>
                    <p class="mt-0.5 text-xs text-glamora-muted">Despacho automático del lote vigente más próximo sin productos vencidos.</p>
                </div>
            </div>

            <div class="flex items-start gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-glamora-cream text-glamora-terracotta">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-sans text-sm font-bold text-glamora-charcoal">Precios B2B Escalonados</h2>
                    <p class="mt-0.5 text-xs text-glamora-muted">Escalas Bronce, Plata y Oro con ahorros de hasta 30% y crédito.</p>
                </div>
            </div>

            <div class="flex items-start gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-glamora-cream text-glamora-terracotta">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-sans text-sm font-bold text-glamora-charcoal">Cobertura Nacional</h2>
                    <p class="mt-0.5 text-xs text-glamora-muted">Same Day en Lima Metropolitana y despacho certificado a provincias.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- 3. CATEGORÍAS PRINCIPALES --}}
    <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">Universo Glamora</span>
                <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal sm:text-4xl">
                    Explora por categoría
                </h2>
            </div>
            <a href="{{ route('catalog.index') }}" class="text-xs font-semibold uppercase tracking-wider text-glamora-charcoal hover:text-glamora-terracotta">
                Ver catálogo completo &rarr;
            </a>
        </div>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($categories as $category)
                <a
                    href="{{ route('catalog.category', $category->slug) }}"
                    class="card-glamora-hover group flex flex-col justify-between p-6"
                >
                    <div>
                        <div class="mb-4 overflow-hidden rounded-xl border border-glamora-blush/30 bg-glamora-cream">
                            <img
                                src="{{ $category->image_url }}"
                                alt="{{ $category->name }}"
                                loading="lazy"
                                class="h-32 w-full object-cover transition duration-300 group-hover:scale-105"
                            >
                        </div>
                        <h3 class="font-serif text-2xl font-semibold text-glamora-charcoal group-hover:text-glamora-terracotta">
                            {{ $category->name }}
                        </h3>
                        <p class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-glamora-muted">
                            {{ $category->description }}
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($category->children->take(3) as $child)
                            <span class="rounded-full bg-glamora-cream px-2.5 py-1 text-[10px] font-medium text-glamora-charcoal">
                                {{ $child->name }}
                            </span>
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- 4. LANZAMIENTOS --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">Recién Llegados</span>
                <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal sm:text-4xl">
                    Lanzamientos de temporada
                </h2>
            </div>
            <a href="{{ route('catalog.index', ['orden' => 'newest']) }}" class="text-xs font-semibold uppercase tracking-wider text-glamora-charcoal hover:text-glamora-terracotta">
                Todos los lanzamientos &rarr;
            </a>
        </div>

        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($newLaunches as $product)
                @include('storefront.partials.product-card', [
                    'product' => $product,
                    'pricingTier' => $pricingTier,
                    'badges' => $productBadges[$product->id] ?? [],
                ])
            @endforeach
        </div>
    </section>

    {{-- 4.5 BANNERS PROMOCIONALES SECUNDARIOS --}}
    @if (isset($promoBanners) && $promoBanners->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ($promoBanners as $promo)
                    <div class="group relative overflow-hidden rounded-[2rem] border border-glamora-blush/40 bg-white shadow-sm">
                        <div class="grid items-center gap-6 p-6 sm:grid-cols-12 sm:p-8">
                            <div class="sm:col-span-7">
                                @if ($promo->eyebrow)
                                    <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-glamora-terracotta">
                                        {{ $promo->eyebrow }}
                                    </span>
                                @endif
                                <h3 class="mt-1.5 font-serif text-2xl font-semibold leading-tight text-glamora-charcoal">
                                    {{ $promo->title }}
                                </h3>
                                @if ($promo->subtitle)
                                    <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-glamora-muted">
                                        {{ $promo->subtitle }}
                                    </p>
                                @endif
                                <div class="mt-4 flex flex-wrap items-center gap-2.5">
                                    @if ($promo->button_text)
                                        <a href="{{ $promo->button_url ?: route('catalog.index') }}" class="btn-glamora-primary px-4 py-2 text-xs">
                                            {{ $promo->button_text }} &rarr;
                                        </a>
                                    @endif
                                    @if ($promo->badge_text)
                                        <span class="rounded-full bg-glamora-cream px-2.5 py-1 text-[10px] font-semibold text-glamora-charcoal">
                                            {{ $promo->badge_text }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="sm:col-span-5">
                                <div class="aspect-square overflow-hidden rounded-2xl border border-glamora-blush/35 bg-glamora-cream">
                                    <img
                                        src="{{ $promo->image_url }}"
                                        alt="{{ $promo->title }}"
                                        loading="lazy"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 5. MARCAS DESTACADAS --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="rounded-[2rem] border border-glamora-blush/35 bg-white p-8 sm:p-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">Casas Curadas</span>
                    <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal sm:text-4xl">
                        Marcas destacadas
                    </h2>
                </div>
                <a href="{{ route('brands.index') }}" class="btn-glamora-outline px-5 py-2 text-xs">
                    Ver directorio de marcas
                </a>
            </div>

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($featuredBrands as $brand)
                    <a
                        href="{{ route('catalog.index', ['marca' => $brand->slug]) }}"
                        class="group rounded-2xl border border-glamora-blush/35 bg-glamora-cream/55 p-5 transition hover:-translate-y-1 hover:border-glamora-gold/60 hover:bg-white hover:shadow-md"
                    >
                        <div class="h-32 w-full overflow-hidden rounded-xl border border-glamora-blush/25 bg-white">
                            <img
                                src="{{ $brand->logo_url }}"
                                alt="{{ $brand->name }}"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            >
                        </div>
                        <div class="mt-4 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-glamora-terracotta">
                            <span>{{ $brand->country_origin }}</span>
                            <span>{{ $brand->products_count }} fórmulas</span>
                        </div>
                        <h3 class="mt-1 font-serif text-xl font-semibold text-glamora-charcoal group-hover:text-glamora-terracotta">
                            {{ $brand->name }}
                        </h3>
                        <p class="mt-1 line-clamp-2 text-xs text-glamora-muted">
                            {{ $brand->description }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 6. KITS MAYORISTAS B2B --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="surface-obsidian overflow-hidden rounded-[2rem] shadow-xl">
            <div class="grid items-center gap-8 p-8 sm:p-10 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <span class="inline-flex items-center gap-2 rounded-full border border-glamora-gold/45 bg-glamora-gold/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-glamora-gold">
                        Canal Profesional B2B
                    </span>
                    <h2 class="mt-4 font-serif text-3xl font-semibold text-white sm:text-4xl">
                        Kits Mayoristas para Salones &amp; Spas
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-glamora-cream/80">
                        Packs cerrados de cabina facial y exhibidores boutique listos para generar ingresos desde el primer día en tu punto de venta.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('wholesale.index') }}" class="btn-glamora-terracotta text-xs uppercase tracking-wider">
                            Solicitar Cuenta RUC
                        </a>
                        <a href="{{ route('catalog.index', ['vista' => 'matrix', 'b2b' => 1]) }}" class="rounded-full border border-white/25 px-5 py-3 text-xs font-semibold text-white transition hover:border-glamora-gold hover:text-glamora-gold">
                            Pedido en Matriz B2B
                        </a>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:col-span-7">
                    @foreach ($b2bKits as $kit)
                        @php
                            $kitVariant = $kit->variants->firstWhere('is_default', true) ?? $kit->variants->first();
                        @endphp
                        <div class="flex flex-col justify-between rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-xs transition hover:border-glamora-gold/45 hover:bg-white/10">
                            <div class="flex items-start gap-4">
                                <img
                                    src="{{ $kit->image_url }}"
                                    alt="{{ $kit->name }}"
                                    loading="lazy"
                                    class="h-20 w-20 shrink-0 rounded-xl border border-white/15 bg-glamora-cream object-cover"
                                >
                                <div class="min-w-0">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-glamora-gold">
                                        {{ $kit->brand?->name }}
                                    </span>
                                    <a href="{{ route('product.show', $kit->slug) }}" class="mt-1 block font-serif text-lg font-semibold leading-snug text-white hover:text-glamora-blush">
                                        {{ $kit->name }}
                                    </a>
                                    <p class="mt-1 line-clamp-2 text-xs text-glamora-cream/70">
                                        {{ $kit->short_description }}
                                    </p>
                                </div>
                            </div>

                            @if ($kitVariant)
                                <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-3.5">
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-glamora-cream/60">
                                            Desde (Mayorista Base)
                                        </span>
                                        <span class="font-serif text-xl font-bold text-glamora-gold">
                                            S/ {{ number_format((float) $kitVariant->wholesale_base_price, 2) }}
                                        </span>
                                        <span class="ml-1.5 text-xs text-glamora-cream/50 line-through">
                                            S/ {{ number_format((float) $kitVariant->base_price, 2) }}
                                        </span>
                                    </div>
                                    <a
                                        href="{{ route('product.show', $kit->slug) }}"
                                        class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-glamora-charcoal transition hover:bg-glamora-gold"
                                    >
                                        Ver Kit
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 7. GUÍAS RECIENTES DEL BLOG --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">Journal Glamora</span>
                <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal sm:text-4xl">
                    Guías editoriales &amp; protocolos
                </h2>
            </div>
            <a href="{{ route('blog.index') }}" class="text-xs font-semibold uppercase tracking-wider text-glamora-charcoal hover:text-glamora-terracotta">
                Ir al Blog &rarr;
            </a>
        </div>

        <div class="mt-8 grid gap-6 md:grid-cols-3">
            @foreach ($recentPosts as $post)
                <article class="card-glamora-hover flex flex-col overflow-hidden">
                    <a href="{{ route('blog.show', $post->slug) }}" class="block aspect-[9/5] overflow-hidden bg-glamora-cream">
                        <img
                            src="{{ $post->cover_url }}"
                            alt="{{ $post->title }}"
                            loading="lazy"
                            class="h-full w-full object-cover transition duration-500 hover:scale-105"
                        >
                    </a>
                    <div class="flex flex-1 flex-col p-6">
                        <div class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-glamora-terracotta">
                            <span>{{ $post->category_tag }}</span>
                            <span class="text-glamora-muted">{{ $post->reading_time_minutes }} min lectura</span>
                        </div>
                        <a href="{{ route('blog.show', $post->slug) }}" class="mt-2 block">
                            <h3 class="font-serif text-2xl font-semibold leading-snug text-glamora-charcoal hover:text-glamora-terracotta">
                                {{ $post->title }}
                            </h3>
                        </a>
                        <p class="mt-2 line-clamp-3 text-sm text-glamora-muted">
                            {{ $post->excerpt }}
                        </p>
                        <div class="mt-auto pt-4">
                            <a href="{{ route('blog.show', $post->slug) }}" class="text-xs font-bold uppercase tracking-wider text-glamora-charcoal hover:text-glamora-terracotta">
                                Leer guía completa &rarr;
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
