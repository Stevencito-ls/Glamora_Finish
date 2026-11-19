@extends('layouts.admin')

@section('title', 'Marketing & Retención — Glamora Perú')
@section('page_title', 'Marketing, Retención & Editorial')
@section('page_subtitle', 'Centro unificado de cupones promocionales, recuperación de carritos abandonados, newsletter opt-in y blog Shop the Story.')

@section('content')
    <div class="space-y-6">
        {{-- Top Summary Cards --}}
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card-glamora p-5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Cupones Vigentes</span>
                    <span class="rounded-full bg-glamora-gold/15 px-2.5 py-0.5 text-[10px] font-bold text-glamora-charcoal">
                        {{ $couponsStats['total_uses'] }} usos
                    </span>
                </div>
                <p class="mt-2 font-serif text-3xl font-bold text-glamora-charcoal">
                    {{ $couponsStats['valid_now'] }} / {{ $couponsStats['total'] }}
                </p>
                <p class="mt-1.5 text-xs text-glamora-muted">
                    Activos actualmente: <strong class="text-glamora-charcoal">{{ $couponsStats['active'] }}</strong> códigos registrados.
                </p>
            </div>

            <div class="card-glamora p-5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Carritos Abandonados</span>
                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">
                        {{ $abandonedStats['eligible'] }} elegibles
                    </span>
                </div>
                <p class="mt-2 font-serif text-3xl font-bold text-glamora-terracotta">
                    S/ {{ number_format($abandonedStats['potential_revenue'], 2) }}
                </p>
                <p class="mt-1.5 text-xs text-glamora-muted">
                    {{ $abandonedStats['unrecovered'] }} sin recuperar · {{ $abandonedStats['recovered'] }} recuperados.
                </p>
            </div>

            <div class="card-glamora p-5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Suscriptores Newsletter</span>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                        {{ $newsletterStats['verified'] }} opt-in
                    </span>
                </div>
                <p class="mt-2 font-serif text-3xl font-bold text-glamora-charcoal">
                    {{ $newsletterStats['active'] }} / {{ $newsletterStats['total'] }}
                </p>
                <p class="mt-1.5 text-xs text-glamora-muted">
                    Base activa segmentada conforme a Ley N.º 29733.
                </p>
            </div>

            <div class="card-glamora p-5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-glamora-muted">Journal &amp; Shop the Story</span>
                    <span class="rounded-full bg-glamora-blush/25 px-2.5 py-0.5 text-[10px] font-bold text-glamora-charcoal">
                        {{ $blogStats['drafts'] }} borradores
                    </span>
                </div>
                <p class="mt-2 font-serif text-3xl font-bold text-glamora-charcoal">
                    {{ $blogStats['published'] }} / {{ $blogStats['total'] }}
                </p>
                <p class="mt-1.5 text-xs text-glamora-muted">
                    Guías publicadas e indexadas en el Sitemap XML.
                </p>
            </div>
        </section>

        {{-- Module Cards --}}
        <section class="grid gap-6 md:grid-cols-2">
            @can('marketing.coupons')
                <div class="card-glamora flex flex-col justify-between p-6">
                    <div>
                        <span class="badge-glamora-gold">Promociones &amp; Segmentación</span>
                        <h2 class="mt-3 font-serif text-2xl font-semibold text-glamora-charcoal">
                            Cupones Promocionales
                        </h2>
                        <p class="mt-1.5 text-sm text-glamora-muted">
                            Gestiona descuentos porcentuales o montos fijos en Soles (PEN), montos mínimos, topes de uso y segmentación exclusiva por nivel Minorista o B2B.
                        </p>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('admin.marketing.coupons.index') }}" class="btn-glamora-primary py-2 px-5 text-xs">
                            Gestionar Cupones &rarr;
                        </a>
                        <a href="{{ route('admin.marketing.coupons.create') }}" class="btn-glamora-outline py-2 px-5 text-xs">
                            + Crear Cupón
                        </a>
                    </div>
                </div>
            @endcan

            @can('marketing.abandoned_carts')
                <div class="card-glamora flex flex-col justify-between p-6">
                    <div>
                        <span class="badge-glamora-blush">Automatización Cron &amp; Cola</span>
                        <h2 class="mt-3 font-serif text-2xl font-semibold text-glamora-charcoal">
                            Carritos Abandonados
                        </h2>
                        <p class="mt-1.5 text-sm text-glamora-muted">
                            Supervisa carritos activos inactivos mayores a 60 minutos, envía recordatorios manuales o despacha la cola elegible respetando cooldown de 24h.
                        </p>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                        <a href="{{ route('admin.marketing.abandoned-carts.index') }}" class="btn-glamora-primary py-2 px-5 text-xs">
                            Ver Carritos ({{ $abandonedStats['eligible'] }} elegibles) &rarr;
                        </a>
                    </div>
                </div>
            @endcan

            @can('marketing.newsletter')
                <div class="card-glamora flex flex-col justify-between p-6">
                    <div>
                        <span class="badge-glamora-gold">Audiencias &amp; Opt-In</span>
                        <h2 class="mt-3 font-serif text-2xl font-semibold text-glamora-charcoal">
                            Suscriptores Newsletter
                        </h2>
                        <p class="mt-1.5 text-sm text-glamora-muted">
                            Filtra suscriptores dinámicamente por segmento ({{ $newsletterStats['by_segment']->keys()->implode(', ') ?: 'general' }}), valida estados opt-in y gestiona bajas.
                        </p>
                    </div>
                    <div class="mt-6">
                        <a href="{{ route('admin.marketing.newsletter.index') }}" class="btn-glamora-primary py-2 px-5 text-xs">
                            Administrar Newsletter &rarr;
                        </a>
                    </div>
                </div>
            @endcan

            @can('marketing.blog')
                <div class="card-glamora flex flex-col justify-between p-6">
                    <div>
                        <span class="badge-glamora-blush">Contenido SEO &amp; Shoppable</span>
                        <h2 class="mt-3 font-serif text-2xl font-semibold text-glamora-charcoal">
                            Blog Editorial &amp; Shop the Story
                        </h2>
                        <p class="mt-1.5 text-sm text-glamora-muted">
                            Redacta guías dermatológicas y de colorimetría, sube portadas seguras, optimiza metadatos SEO y vincula productos en <code class="font-mono text-xs">post_product</code>.
                        </p>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('admin.marketing.blog.index') }}" class="btn-glamora-primary py-2 px-5 text-xs">
                            Gestionar Artículos &rarr;
                        </a>
                        <a href="{{ route('admin.marketing.blog.create') }}" class="btn-glamora-outline py-2 px-5 text-xs">
                            + Nuevo Artículo
                        </a>
                    </div>
                </div>
            @endcan
        </section>
    </div>
@endsection
