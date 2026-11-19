@extends('layouts.storefront')

@section('title', $activeCategory ? ($activeCategory->meta_title ?: "{$activeCategory->name} — Catálogo Glamora Perú") : 'Catálogo de Maquillaje, Skincare & B2B — Glamora Perú')
@section('meta_description', $activeCategory ? ($activeCategory->meta_description ?: ($activeCategory->description ?: "Explora {$activeCategory->name} en Glamora Perú con precios minoristas, escalas B2B y stock FEFO verificado.")) : 'Explora nuestro catálogo de maquillaje editorial, skincare botánico y kits profesionales B2B en Perú con trazabilidad FEFO.')
@section('canonical_url', $activeCategory ? route('catalog.category', $activeCategory->slug) : route('catalog.index'))

@push('structured_data')
    @php
        $catalogUrl = $activeCategory ? route('catalog.category', $activeCategory->slug) : route('catalog.index');
        $collectionSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $activeCategory ? $activeCategory->name : 'Catálogo Glamora Perú',
            'description' => $activeCategory
                ? ($activeCategory->meta_description ?: ($activeCategory->description ?: "Productos de {$activeCategory->name} en Glamora Perú."))
                : 'Catálogo completo de maquillaje editorial, skincare botánico y kits B2B en Glamora Perú.',
            'url' => $catalogUrl,
        ];

        $breadcrumbs = [
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

        if ($activeCategory) {
            if ($activeCategory->parent) {
                $breadcrumbs[] = [
                    '@type' => 'ListItem',
                    'position' => count($breadcrumbs) + 1,
                    'name' => $activeCategory->parent->name,
                    'item' => route('catalog.category', $activeCategory->parent->slug),
                ];
            }

            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => count($breadcrumbs) + 1,
                'name' => $activeCategory->name,
                'item' => route('catalog.category', $activeCategory->slug),
            ];
        }

        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs,
        ];
    @endphp
    <script type="application/ld+json">@json($collectionSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
    <script type="application/ld+json">@json($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
@endpush

@section('content')
    <section class="border-b border-glamora-blush/35 bg-glamora-hero">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <nav aria-label="Breadcrumb" class="mb-3 flex items-center gap-2 text-xs text-glamora-muted">
                <a href="{{ route('home') }}" class="hover:text-glamora-terracotta">Inicio</a>
                <span>/</span>
                <a href="{{ route('catalog.index') }}" class="hover:text-glamora-terracotta">Catálogo</a>
                @if ($activeCategory)
                    @if ($activeCategory->parent)
                        <span>/</span>
                        <a href="{{ route('catalog.category', $activeCategory->parent->slug) }}" class="hover:text-glamora-terracotta">
                            {{ $activeCategory->parent->name }}
                        </a>
                    @endif
                    <span>/</span>
                    <span class="font-semibold text-glamora-charcoal">{{ $activeCategory->name }}</span>
                @endif
            </nav>

            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="flex flex-wrap items-center gap-5">
                    @if ($activeCategory)
                        <img
                            src="{{ $activeCategory->image_url }}"
                            alt="{{ $activeCategory->name }}"
                            class="h-20 w-20 shrink-0 rounded-2xl border border-glamora-blush/40 bg-glamora-cream object-cover shadow-xs"
                        >
                    @endif
                    <div>
                        <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">
                            Fórmulas Verificadas · Control FEFO
                        </span>
                        <h1 class="mt-1 font-serif text-4xl font-semibold text-glamora-charcoal sm:text-5xl">
                            {{ $activeCategory ? $activeCategory->name : 'Catálogo Glamora' }}
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm text-glamora-muted">
                            {{ $activeCategory?->description ?: 'Explora en Cuadrícula Visual o cambia a la Matriz de Pedido Rápido por Tonos para reabastecer múltiples SKUs al instante.' }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a
                        href="{{ route('catalog.pdf', array_filter([
                            'categoria' => $initialCategory ?: null,
                            'marca' => $initialBrand ?: null,
                            'b2b' => $initialOnlyB2b ? 1 : null,
                        ])) }}"
                        class="btn-glamora-outline px-4 py-2 text-xs uppercase tracking-wider"
                    >
                        <svg class="h-4 w-4 text-glamora-terracotta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0-3-3m3 3 3-3m2 8.25H7a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 7 3h5.586a1 1 0 0 1 .707.293l4.414 4.414a1 1 0 0 1 .293.707V19.5a2.25 2.25 0 0 1-2.25 2.25Z" />
                        </svg>
                        Descargar Catálogo PDF
                    </a>
                    <a href="{{ route('wholesale.index') }}" class="badge-glamora-gold">
                        Escala B2B hasta 30% OFF
                    </a>
                </div>
            </div>
        </div>
    </section>

    <livewire:storefront.catalog-browser
        :initial-category="$initialCategory"
        :initial-brand="$initialBrand"
        :initial-search="$initialSearch"
        :initial-only-b2b="$initialOnlyB2b"
        :initial-view-mode="$initialViewMode"
    />
@endsection
