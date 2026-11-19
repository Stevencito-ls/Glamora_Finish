@extends('layouts.storefront')

@section('title', $post->meta_title ?: "{$post->title} — Blog Glamora")
@section('meta_description', $post->meta_description ?: ($post->excerpt ?: $post->title))
@section('canonical_url', route('blog.show', $post->slug))
@section('og_type', 'article')
@section('og_image', $post->cover_url)

@push('structured_data')
    @php
        $articleImage = $post->cover_url;
        $articleSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->meta_description ?: ($post->excerpt ?: $post->title),
            'image' => [$articleImage],
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => ($post->updated_at ?? $post->published_at)?->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->name ?? 'Equipo Editorial Glamora',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Glamora Perú',
                'url' => route('home'),
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => route('blog.show', $post->slug),
            ],
        ];
    @endphp
    <script type="application/ld+json">@json($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
@endpush

@section('content')
    <article class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        {{-- Breadcrumb --}}
        <nav aria-label="Breadcrumb" class="mb-6 flex items-center gap-2 text-xs text-glamora-muted">
            <a href="{{ route('home') }}" class="hover:text-glamora-terracotta">Inicio</a>
            <span>/</span>
            <a href="{{ route('blog.index') }}" class="hover:text-glamora-terracotta">Blog</a>
            <span>/</span>
            @if ($post->category_tag)
                <a href="{{ route('blog.index', ['etiqueta' => $post->category_tag]) }}" class="hover:text-glamora-terracotta">
                    {{ $post->category_tag }}
                </a>
                <span>/</span>
            @endif
            <span class="truncate font-semibold text-glamora-charcoal">{{ $post->title }}</span>
        </nav>

        {{-- Header --}}
        <header class="mx-auto max-w-4xl text-center">
            <div class="flex flex-wrap items-center justify-center gap-3 text-xs font-semibold uppercase tracking-[0.18em] text-glamora-terracotta">
                <span>{{ $post->category_tag }}</span>
                <span class="text-glamora-muted">·</span>
                <span class="text-glamora-muted">{{ $post->reading_time_minutes }} min lectura</span>
                @if ($post->published_at)
                    <span class="text-glamora-muted">·</span>
                    <span class="text-glamora-muted">{{ $post->published_at->format('d M Y') }}</span>
                @endif
            </div>

            <h1 class="mt-3 font-serif text-4xl font-semibold leading-tight text-glamora-charcoal sm:text-5xl">
                {{ $post->title }}
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-glamora-muted">
                {{ $post->excerpt }}
            </p>
        </header>

        {{-- Cover Image --}}
        <div class="mt-8 overflow-hidden rounded-[2rem] border border-glamora-blush/40 bg-white p-2 shadow-sm">
            <img
                src="{{ $post->cover_url }}"
                alt="{{ $post->title }}"
                class="aspect-[9/5] w-full rounded-[1.5rem] object-cover"
            >
        </div>

        {{-- Body + Embedded Shoppable Products --}}
        <div class="mt-12 grid gap-10 lg:grid-cols-12">
            {{-- Editorial Content --}}
            <div class="lg:col-span-7">
                <div class="card-glamora p-7 sm:p-10">
                    <div class="prose-glamora">
                        {!! $post->safeHtmlContent() !!}
                    </div>

                    <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-glamora-blush/30 pt-6 text-xs text-glamora-muted">
                        <span>
                            Publicado por <strong class="text-glamora-charcoal">{{ $post->author?->name ?? 'Equipo Editorial Glamora' }}</strong>
                        </span>
                        <a href="{{ route('blog.index') }}" class="font-semibold text-glamora-terracotta hover:underline">
                            &larr; Volver al Blog
                        </a>
                    </div>
                </div>
            </div>

            {{-- Shoppable Embedded Cards --}}
            <aside class="lg:col-span-5">
                <div class="sticky top-24 space-y-4">
                    <div class="rounded-2xl border border-glamora-gold/40 bg-glamora-charcoal px-5 py-4 text-glamora-cream">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-glamora-gold">
                            Shop the Story
                        </span>
                        <h2 class="mt-0.5 font-serif text-2xl font-semibold text-white">
                            Productos recomendados en esta guía
                        </h2>
                    </div>

                    @forelse ($post->products as $embeddedProduct)
                        @php
                            $defaultVar = $embeddedProduct->variants->firstWhere('is_default', true) ?? $embeddedProduct->variants->first();
                            $stock = $defaultVar ? $defaultVar->availableStock() : 0;
                            $unitPrice = $defaultVar ? $defaultVar->priceForTier($pricingTier, 1) : 0.0;
                        @endphp
                        <div class="card-glamora p-4">
                            @if ($embeddedProduct->pivot?->custom_callout)
                                <p class="mb-3 rounded-xl bg-glamora-cream px-3 py-1.5 text-xs font-semibold text-glamora-terracotta">
                                    {{ $embeddedProduct->pivot->custom_callout }}
                                </p>
                            @endif

                            <div class="flex gap-4">
                                <a href="{{ route('product.show', $embeddedProduct->slug) }}" class="h-24 w-24 shrink-0 overflow-hidden rounded-xl border border-glamora-blush/30 bg-glamora-cream">
                                    <img
                                        src="{{ $embeddedProduct->image_url }}"
                                        alt="{{ $embeddedProduct->name }}"
                                        loading="lazy"
                                        class="h-full w-full object-cover"
                                    >
                                </a>

                                <div class="flex min-w-0 flex-1 flex-col justify-between">
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">
                                            {{ $embeddedProduct->brand?->name }}
                                        </span>
                                        <a href="{{ route('product.show', $embeddedProduct->slug) }}" class="mt-0.5 block font-serif text-lg font-semibold leading-snug text-glamora-charcoal hover:text-glamora-terracotta">
                                            {{ $embeddedProduct->name }}
                                        </a>
                                        @if ($defaultVar)
                                            @php
                                                $safeVarHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $defaultVar->hex_color) === 1
                                                    ? (string) $defaultVar->hex_color
                                                    : '#C87D72';
                                            @endphp
                                            <div class="mt-1 flex items-center gap-1.5 text-xs text-glamora-muted">
                                                <span class="h-3 w-3 rounded-full border border-black/15" style="background-color: {{ $safeVarHex }}"></span>
                                                <span>{{ $defaultVar->shade_name }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($defaultVar)
                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                            <span class="font-serif text-xl font-bold text-glamora-charcoal">
                                                S/ {{ number_format($unitPrice, 2) }}
                                            </span>

                                            <div class="flex items-center gap-2">
                                                <a
                                                    href="{{ route('product.show', $embeddedProduct->slug) }}"
                                                    class="rounded-full border border-glamora-charcoal/20 px-3 py-1.5 text-[11px] font-semibold text-glamora-charcoal hover:bg-glamora-charcoal hover:text-white"
                                                >
                                                    Ver
                                                </a>

                                                <form action="{{ route('cart.add') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="variant_id" value="{{ $defaultVar->id }}">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <button
                                                        type="submit"
                                                        @disabled($stock <= 0)
                                                        class="rounded-full bg-glamora-terracotta px-3.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-[#b5695e] disabled:opacity-45"
                                                    >
                                                        + Agregar
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="card-glamora p-5 text-xs text-glamora-muted">
                            Explora todo el portafolio en nuestro <a href="{{ route('catalog.index') }}" class="font-semibold text-glamora-terracotta underline">catálogo completo</a>.
                        </div>
                    @endforelse
                </div>
            </aside>
        </div>

        {{-- Related Posts --}}
        @if ($relatedPosts->isNotEmpty())
            <section class="mt-16 border-t border-glamora-blush/35 pt-12">
                <h2 class="font-serif text-3xl font-semibold text-glamora-charcoal">
                    Sigue leyendo en el Journal Glamora
                </h2>
                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    @foreach ($relatedPosts as $rel)
                        <a href="{{ route('blog.show', $rel->slug) }}" class="card-glamora-hover flex flex-col overflow-hidden sm:flex-row">
                            <img
                                src="{{ $rel->cover_url }}"
                                alt="{{ $rel->title }}"
                                loading="lazy"
                                class="h-44 w-full object-cover sm:h-auto sm:w-48"
                            >
                            <div class="flex flex-1 flex-col justify-center p-5">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-glamora-terracotta">
                                    {{ $rel->category_tag }} · {{ $rel->reading_time_minutes }} min
                                </span>
                                <h3 class="mt-1 font-serif text-xl font-semibold text-glamora-charcoal">
                                    {{ $rel->title }}
                                </h3>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
@endsection
