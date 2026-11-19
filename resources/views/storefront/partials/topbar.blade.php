@php
    $announcements = ($activeAnnouncements ?? collect())->values();
    $freeThreshold = (float) ($storeSettings?->free_shipping_threshold ?? \App\Services\ShippingService::FREE_SHIPPING_THRESHOLD);
@endphp

<div class="bg-glamora-charcoal text-glamora-cream">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-[11px] tracking-wider sm:px-6 lg:px-8">
        {{-- Carrusel Rotativo de Anuncios / Fallback Dinámico --}}
        <div
            @if ($announcements->isNotEmpty())
                x-data="{
                    current: 0,
                    total: {{ $announcements->count() }},
                    paused: false,
                    next() { this.current = (this.current + 1) % this.total; },
                    prev() { this.current = (this.current - 1 + this.total) % this.total; },
                    init() {
                        if (this.total > 1) {
                            setInterval(() => {
                                if (!this.paused) this.next();
                            }, 5000);
                        }
                    }
                }"
                @mouseenter="paused = true"
                @mouseleave="paused = false"
            @endif
            class="flex min-w-0 flex-1 items-center gap-2"
        >
            @if ($announcements->isNotEmpty())
                @if ($announcements->count() > 1)
                    <button
                        type="button"
                        @click="prev()"
                        aria-label="Anuncio anterior"
                        class="hidden h-5 w-5 shrink-0 items-center justify-center rounded-full border border-white/15 text-[10px] text-glamora-cream/75 transition hover:border-glamora-gold hover:text-glamora-gold sm:inline-flex"
                    >
                        &lsaquo;
                    </button>
                @endif

                <div class="relative min-w-0 flex-1">
                    @foreach ($announcements as $idx => $announcement)
                        @php
                            $safeUrl = method_exists($announcement, 'safeLinkUrl') ? $announcement->safeLinkUrl() : null;
                        @endphp
                        <div
                            x-show="current === {{ $idx }}"
                            @if ($idx > 0) x-cloak @endif
                            x-transition.opacity.duration.300ms
                            class="flex flex-wrap items-center gap-x-2.5 gap-y-1"
                        >
                            @if ($announcement->badge_text)
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-glamora-gold/40 bg-glamora-gold/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-glamora-gold">
                                    <span class="h-1.5 w-1.5 rounded-full bg-glamora-gold"></span>
                                    {{ $announcement->badge_text }}
                                </span>
                            @endif

                            <span class="truncate text-glamora-cream/90">
                                {{ $announcement->message }}
                            </span>

                            @if ($safeUrl)
                                <a
                                    href="{{ $safeUrl }}"
                                    class="shrink-0 font-semibold text-glamora-gold underline decoration-glamora-gold/40 underline-offset-2 transition hover:text-glamora-blush"
                                >
                                    {{ $announcement->link_label ?: 'Ver más' }} &rarr;
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($announcements->count() > 1)
                    <button
                        type="button"
                        @click="next()"
                        aria-label="Siguiente anuncio"
                        class="hidden h-5 w-5 shrink-0 items-center justify-center rounded-full border border-white/15 text-[10px] text-glamora-cream/75 transition hover:border-glamora-gold hover:text-glamora-gold sm:inline-flex"
                    >
                        &rsaquo;
                    </button>
                @endif
            @else
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span class="inline-flex items-center gap-1.5 font-medium text-glamora-gold">
                        <span class="h-1.5 w-1.5 rounded-full bg-glamora-gold"></span>
                        DIGEMID Verificado
                    </span>
                    <span class="hidden text-glamora-cream/80 md:inline">
                        Trazabilidad FEFO por lote · Envío Gratis desde S/ {{ number_format($freeThreshold, 2) }}
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
