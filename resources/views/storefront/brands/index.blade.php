@extends('layouts.storefront')

@section('title', 'Marcas Curadas — Glamora Perú')
@section('meta_description', 'Conoce las marcas curadas de alta cosmética botánica y dermocosmética en Glamora Perú con registro sanitario DIGEMID y certificación Cruelty-Free.')
@section('canonical_url', route('brands.index'))

@push('structured_data')
    @php
        $brandItems = $brands->values()->map(fn ($brand, int $index): array => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $brand->name,
            'url' => route('catalog.index', ['marca' => $brand->slug]),
        ])->all();

        $brandsCollectionSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => 'Marcas Curadas — Glamora Perú',
            'url' => route('brands.index'),
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => count($brandItems),
                'itemListElement' => $brandItems,
            ],
        ];
    @endphp
    <script type="application/ld+json">@json($brandsCollectionSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
@endpush

@section('content')
    <section class="border-b border-glamora-blush/35 bg-gradient-to-r from-[#FAF6F2] via-white to-[#F7ECE4]">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">
                Portafolio Oficial
            </span>
            <h1 class="mt-2 font-serif text-4xl font-semibold text-glamora-charcoal sm:text-5xl">
                Marcas curadas de alta cosmética
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-glamora-muted">
                Trabajamos directamente con laboratorios peruanos, coreanos y europeos certificados Cruelty-Free, con registro sanitario DIGEMID vigente para venta minorista y distribución B2B.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="space-y-8">
            @foreach ($brands as $brand)
                <article class="card-glamora overflow-hidden p-6 sm:p-8">
                    <div class="grid items-center gap-8 lg:grid-cols-12">
                        {{-- Brand Identity --}}
                        <div class="lg:col-span-5">
                            <div class="relative overflow-hidden rounded-2xl border border-glamora-blush/35 bg-glamora-cream">
                                <img
                                    src="{{ $brand->banner_url }}"
                                    alt="{{ $brand->name }}"
                                    loading="lazy"
                                    class="h-44 w-full object-cover"
                                >
                                <div class="absolute bottom-3 left-3 flex items-center gap-3 rounded-xl border border-white/40 bg-white/95 p-2 shadow-md backdrop-blur-xs">
                                    <img
                                        src="{{ $brand->logo_url }}"
                                        alt="{{ $brand->name }}"
                                        loading="lazy"
                                        class="h-12 w-12 rounded-lg object-cover"
                                    >
                                    <div class="pr-2">
                                        <p class="font-serif text-base font-bold leading-none text-glamora-charcoal">{{ $brand->name }}</p>
                                        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wider text-glamora-terracotta">{{ $brand->country_origin }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="badge-glamora-gold">
                                    Origen: {{ $brand->country_origin }}
                                </span>
                                @if ($brand->is_cruelty_free)
                                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-emerald-800">
                                        Cruelty-Free
                                    </span>
                                @endif
                                <span class="rounded-full bg-glamora-cream px-3 py-1 text-[11px] font-semibold text-glamora-charcoal">
                                    {{ $brand->products_count }} {{ $brand->products_count === 1 ? 'producto' : 'productos' }}
                                </span>
                            </div>

                            <h2 class="mt-4 font-serif text-3xl font-semibold text-glamora-charcoal">
                                {{ $brand->name }}
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-glamora-muted">
                                {{ $brand->description }}
                            </p>

                            <div class="mt-6">
                                <a
                                    href="{{ route('catalog.index', ['marca' => $brand->slug]) }}"
                                    class="btn-glamora-primary text-xs uppercase tracking-wider"
                                >
                                    Explorar catálogo de {{ $brand->name }} &rarr;
                                </a>
                            </div>
                        </div>

                        {{-- Highlighted Products of Brand --}}
                        <div class="lg:col-span-7">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-xs font-bold uppercase tracking-[0.18em] text-glamora-muted">
                                    Fórmulas destacadas
                                </h3>
                                <a href="{{ route('catalog.index', ['marca' => $brand->slug, 'vista' => 'matrix']) }}" class="text-xs font-semibold text-glamora-terracotta hover:underline">
                                    Ver matriz B2B &rarr;
                                </a>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                @foreach ($brand->products->take(3) as $prod)
                                    @php
                                        $defVar = $prod->variants->firstWhere('is_default', true) ?? $prod->variants->first();
                                    @endphp
                                    <a
                                        href="{{ route('product.show', $prod->slug) }}"
                                        class="group flex flex-col rounded-2xl border border-glamora-blush/35 bg-glamora-cream/55 p-3.5 transition hover:border-glamora-gold/60 hover:bg-white hover:shadow-md"
                                    >
                                        <img
                                            src="{{ $prod->image_url }}"
                                            alt="{{ $prod->name }}"
                                            loading="lazy"
                                            class="aspect-square w-full rounded-xl bg-white object-cover transition duration-300 group-hover:scale-105"
                                        >
                                        <span class="mt-3 text-[10px] font-semibold uppercase tracking-wider text-glamora-terracotta">
                                            {{ $prod->category?->name }}
                                        </span>
                                        <h4 class="mt-1 line-clamp-2 font-serif text-base font-semibold text-glamora-charcoal group-hover:text-glamora-terracotta">
                                            {{ $prod->name }}
                                        </h4>
                                        @if ($defVar)
                                            <p class="mt-auto pt-2 text-xs font-bold text-glamora-charcoal">
                                                S/ {{ number_format((float) $defVar->base_price, 2) }}
                                            </p>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
