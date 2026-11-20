@php
    $announcements = ($activeAnnouncements ?? collect())->values();
    $shippingMin = (float) ($freeShippingThreshold ?? \App\Services\ShippingService::FREE_SHIPPING_THRESHOLD);
@endphp

<div class="bg-glamora-charcoal text-glamora-cream">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-2 text-[11px] tracking-wider sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-6 lg:px-8">
        {{-- Left / Announcements Rotator (auto-height flex, zero clipping on 360px) --}}
        <div
            @if ($announcements->count() > 1)
                x-data="{
                    active: 0,
                    count: {{ $announcements->count() }},
                    paused: false,
                    next() { this.active = (this.active + 1) % this.count; },
                    prev() { this.active = (this.active - 1 + this.count) % this.count; },
                    init() {
                        setInterval(() => {
                            if (!this.paused) this.next();
                        }, 5500);
                    }
                }"
                @mouseenter="paused = true"
                @mouseleave="paused = false"
            @else
                x-data="{ active: 0 }"
            @endif
            class="flex min-w-0 flex-1 items-center gap-2"
        >
            @if ($announcements->isNotEmpty())
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    @foreach ($announcements as $idx => $announcement)
                        @php
                            $safeHref = $announcement->safeLinkUrl();
                        @endphp
                        <div
                            x-show="active === {{ $idx }}"
                            @if ($idx > 0) x-cloak @endif
                            class="flex min-w-0 flex-wrap items-center gap-x-2.5 gap-y-1 leading-snug"
                        >
                            @if ($announcement->badge_text)
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-glamora-gold/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-glamora-gold">
                                    <span class="h-1.5 w-1.5 rounded-full bg-glamora-gold"></span>
                                    {{ $announcement->badge_text }}
                                </span>
                            @endif

                            <span class="text-glamora-cream/90">
                                {{ $announcement->title }}
                            </span>

                            @if ($safeHref)
                                <a
                                    href="{{ $safeHref }}"
                                    class="shrink-0 font-semibold text-glamora-gold underline decoration-glamora-gold/50 underline-offset-2 transition hover:text-glamora-blush"
                                >
                                    {{ $announcement->link_label ?: 'Ver más' }} &rarr;
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($announcements->count() > 1)
                    <div class="hidden shrink-0 items-center gap-1 sm:flex">
                        <button
                            type="button"
                            @click="prev()"
                            aria-label="Anuncio anterior"
                            class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-white/15 text-[10px] text-glamora-cream/80 transition hover:border-glamora-gold hover:text-glamora-gold"
                        >
                            &lsaquo;
                        </button>
                        <button
                            type="button"
                            @click="next()"
                            aria-label="Siguiente anuncio"
                            class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-white/15 text-[10px] text-glamora-cream/80 transition hover:border-glamora-gold hover:text-glamora-gold"
                        >
                            &rsaquo;
                        </button>
                    </div>
                @endif
            @else
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="inline-flex items-center gap-1.5 font-medium text-glamora-gold">
                        <span class="h-1.5 w-1.5 rounded-full bg-glamora-gold"></span>
                        DIGEMID Verificado
                    </span>
                    <span class="text-glamora-cream/85">
                        Trazabilidad FEFO por lote · Envío Gratis desde S/ {{ number_format($shippingMin, 2) }}
                    </span>
                </div>
            @endif
        </div>

        {{-- Right Links --}}
        <div class="flex shrink-0 flex-wrap items-center gap-3 text-glamora-cream/85 sm:gap-4">
            <a href="{{ route('wholesale.index') }}" class="transition hover:text-glamora-gold">
                Atención Mayorista B2B
            </a>
            <span class="text-glamora-cream/30">|</span>
            <a href="{{ route('tracking.index') }}" class="transition hover:text-glamora-gold">
                Rastrear Pedido
            </a>
            <span class="hidden text-glamora-cream/30 sm:inline">|</span>
            @auth
                @if (Route::has('admin.dashboard') && auth()->user()->is_active && auth()->user()->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero', 'Vendedor B2B']))
                    <a href="{{ route('admin.dashboard') }}" class="font-semibold text-glamora-gold transition hover:text-glamora-blush">
                        Panel Admin
                    </a>
                    <span class="hidden text-glamora-cream/30 sm:inline">|</span>
                @endif
                <a href="{{ route('account.index') }}" class="hidden transition hover:text-glamora-gold sm:inline">
                    Mi Cuenta ({{ auth()->user()->effectiveTier()->label() }})
                </a>
            @else
                <a href="{{ route('login') }}" class="hidden transition hover:text-glamora-gold sm:inline">
                    Iniciar Sesión
                </a>
                <span class="hidden text-glamora-cream/30 sm:inline">·</span>
                <a href="{{ route('register') }}" class="hidden transition hover:text-glamora-gold sm:inline">
                    Registro
                </a>
            @endauth
        </div>
    </div>
</div>
