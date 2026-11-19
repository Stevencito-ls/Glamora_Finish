@extends('layouts.storefront')

@section('title', 'Blog Editorial & Ciencia Cosmética — Glamora Perú')
@section('meta_description', 'Journal editorial Glamora Perú: rutinas de skincare botánico, guías de colorimetría y gestión rentable para salones y spas B2B.')
@section('canonical_url', route('blog.index'))

@push('structured_data')
    @php
        $blogPostsSchema = $posts->getCollection()->map(fn ($post): array => [
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'url' => route('blog.show', $post->slug),
            'datePublished' => $post->published_at?->toIso8601String(),
        ])->values()->all();

        $blogSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => 'Blog Editorial & Ciencia Cosmética — Glamora Perú',
            'url' => route('blog.index'),
            'blogPost' => $blogPostsSchema,
        ];
    @endphp
    <script type="application/ld+json">@json($blogSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
@endpush

@section('content')
    <section class="border-b border-glamora-blush/35 bg-gradient-to-r from-[#FAF6F2] via-white to-[#F7ECE4]">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">
                Journal Editorial Glamora
            </span>
            <h1 class="mt-2 font-serif text-4xl font-semibold text-glamora-charcoal sm:text-5xl">
                Guías de skincare, colorimetría &amp; gestión B2B
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-glamora-muted">
                Artículos formulados por especialistas en dermocosmética y colorimetría con productos recomendados listos para agregar a tu bolsa.
            </p>

            {{-- Filter by Tag --}}
            <div class="mt-7 flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('blog.index') }}"
                    @class([
                        'rounded-full px-4 py-2 text-xs font-semibold transition',
                        'bg-glamora-charcoal text-glamora-cream' => $activeTag === '',
                        'border border-glamora-charcoal/15 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $activeTag !== '',
                    ])
                >
                    Todos los artículos
                </a>

                @foreach ($tags as $tag)
                    <a
                        href="{{ route('blog.index', ['etiqueta' => $tag]) }}"
                        @class([
                            'rounded-full px-4 py-2 text-xs font-semibold transition',
                            'bg-glamora-charcoal text-glamora-cream' => $activeTag === $tag,
                            'border border-glamora-charcoal/15 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $activeTag !== $tag,
                        ])
                    >
                        {{ $tag }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        @if ($posts->count() > 0)
            <div class="grid gap-8 md:grid-cols-3">
                @foreach ($posts as $post)
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

                            <a href="{{ route('blog.show', $post->slug) }}" class="mt-2.5 block">
                                <h2 class="font-serif text-2xl font-semibold leading-snug text-glamora-charcoal hover:text-glamora-terracotta">
                                    {{ $post->title }}
                                </h2>
                            </a>

                            <p class="mt-2.5 line-clamp-3 text-sm leading-relaxed text-glamora-muted">
                                {{ $post->excerpt }}
                            </p>

                            <div class="mt-auto flex items-center justify-between border-t border-glamora-blush/25 pt-4 text-xs text-glamora-muted">
                                <span>{{ $post->published_at?->format('d/m/Y') }}</span>
                                <a href="{{ route('blog.show', $post->slug) }}" class="font-bold uppercase tracking-wider text-glamora-charcoal hover:text-glamora-terracotta">
                                    Leer artículo &rarr;
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $posts->links() }}
            </div>
        @else
            <div class="card-glamora p-12 text-center">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                    No hay publicaciones para esta etiqueta
                </h2>
                <a href="{{ route('blog.index') }}" class="btn-glamora-primary mt-5">
                    Ver todas las guías
                </a>
            </div>
        @endif
    </section>
@endsection
