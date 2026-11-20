@php
    $navItems = [
        ['label' => 'Inicio', 'route' => 'home', 'pattern' => 'home'],
        ['label' => 'Catálogo', 'route' => 'catalog.index', 'pattern' => 'catalog.*'],
        ['label' => 'Marcas', 'route' => 'brands.index', 'pattern' => 'brands.*'],
        ['label' => 'Mayoristas', 'route' => 'wholesale.index', 'pattern' => 'wholesale.*'],
        ['label' => 'Blog', 'route' => 'blog.index', 'pattern' => 'blog.*'],
        ['label' => 'Seguimiento', 'route' => 'tracking.index', 'pattern' => 'tracking.*'],
    ];

    $brandName = trim((string) ($storeSettings['store_name'] ?? 'Glamora Perú')) ?: 'Glamora Perú';
    $brandTagline = trim((string) ($storeSettings['store_tagline'] ?? 'Perú · Atelier & B2B')) ?: 'Perú · Atelier & B2B';
    $brandInitial = mb_strtoupper(mb_substr($brandName, 0, 1));
    $showCatalogPdf = (bool) ($catalogPdfEnabled ?? true);
@endphp

<header
    x-data="{
        mobileOpen: false,
        mobileSearchOpen: false,
        toggleMobileMenu() {
            this.mobileOpen = !this.mobileOpen;
            if (this.mobileOpen) this.mobileSearchOpen = false;
        },
        toggleMobileSearch() {
            this.mobileSearchOpen = !this.mobileSearchOpen;
            if (this.mobileSearchOpen) this.mobileOpen = false;
        }
    }"
    class="sticky top-0 z-50 border-b border-glamora-blush/30"
>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-glamora-cream/95 backdrop-blur-md"></div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Fila 1: Brand Lockup + LiveSearch Centrado (Desktop) + Utilidades --}}
        <div class="flex items-center justify-between gap-4 py-3">
            {{-- Brand Lockup --}}
            <a href="{{ route('home') }}" class="group flex shrink-0 items-center gap-3">
                @if (! empty($storeLogoUrl))
                    <img
                        src="{{ $storeLogoUrl }}"
                        alt="{{ $brandName }}"
                        class="h-10 w-auto max-w-[140px] rounded-lg object-contain"
                    >
                @else
                    <span class="flex h-10 w-10 items-center justify-center rounded-full border border-glamora-gold/60 bg-glamora-charcoal font-serif text-xl font-semibold text-glamora-gold transition group-hover:bg-glamora-terracotta group-hover:text-white">
                        {{ $brandInitial }}
                    </span>
                @endif
                <div class="leading-none">
                    <span class="block font-serif text-xl font-semibold tracking-[0.18em] text-glamora-charcoal sm:text-2xl">
                        {{ mb_strtoupper($brandName) }}
                    </span>
                    <span class="mt-0.5 block max-w-[190px] truncate text-[9px] font-semibold uppercase tracking-[0.22em] text-glamora-terracotta">
                        {{ $brandTagline }}
                    </span>
                </div>
            </a>

            {{-- Centered Desktop LiveSearch --}}
            <div class="hidden lg:block flex-1 max-w-md mx-auto">
                <livewire:storefront.live-search />
            </div>

            {{-- Right Utilities --}}
            <div class="flex shrink-0 items-center gap-2">
                @if ($showCatalogPdf)
                    <a
                        href="{{ route('catalog.pdf') }}"
                        target="_blank"
                        rel="noopener"
                        class="hidden items-center gap-1.5 rounded-full border border-glamora-blush/60 bg-white px-3.5 py-2 text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta hover:text-glamora-terracotta xl:inline-flex"
                    >
                        <svg class="h-3.5 w-3.5 text-glamora-terracotta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0-3-3m3 3 3-3m2 8.25H7a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 7 3h5.586a1 1 0 0 1 .707.293l4.414 4.414a1 1 0 0 1 .293.707V19.5a2.25 2.25 0 0 1-2.25 2.25Z" />
                        </svg>
                        <span>Catálogo PDF</span>
                    </a>
                @endif

                @if (Route::has('admin.dashboard'))
                    @auth
                        @if (auth()->user()->is_active && auth()->user()->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero', 'Vendedor B2B']))
                            <a
                                href="{{ route('admin.dashboard') }}"
                                class="hidden rounded-full border border-glamora-gold/50 bg-glamora-gold/10 px-3.5 py-2 text-xs font-semibold text-glamora-charcoal transition hover:bg-glamora-gold/20 sm:inline-flex"
                            >
                                Panel Admin
                            </a>
                        @endif
                    @endauth
                @endif

                @auth
                    <a
                        href="{{ route('account.index') }}"
                        class="hidden rounded-full border border-glamora-charcoal/15 bg-white px-3.5 py-2 text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta sm:inline-flex"
                    >
                        Mi Cuenta
                    </a>
                    <form action="{{ route('logout') }}" method="POST" class="hidden sm:inline-flex">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-full border border-glamora-charcoal/15 bg-white px-3 py-2 text-xs font-semibold text-glamora-muted transition hover:border-rose-300 hover:text-rose-700"
                        >
                            Salir
                        </button>
                    </form>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="hidden rounded-full border border-glamora-charcoal/15 bg-white px-3.5 py-2 text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta sm:inline-flex"
                    >
                        Ingresar
                    </a>
                    <a
                        href="{{ route('register') }}"
                        class="hidden rounded-full border border-glamora-terracotta/40 bg-glamora-terracotta/10 px-3.5 py-2 text-xs font-semibold text-glamora-terracotta transition hover:bg-glamora-terracotta hover:text-white md:inline-flex"
                    >
                        Crear Cuenta
                    </a>
                @endauth

                <livewire:storefront.cart-counter-drawer />

                {{-- Mobile Search Toggle --}}
                <button
                    type="button"
                    @click="toggleMobileSearch()"
                    :aria-expanded="mobileSearchOpen.toString()"
                    aria-label="Buscar en tienda"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-glamora-charcoal/15 bg-white text-glamora-charcoal transition hover:border-glamora-terracotta lg:hidden"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                    </svg>
                </button>

                {{-- Mobile Menu Toggle --}}
                <button
                    type="button"
                    @click="toggleMobileMenu()"
                    :aria-expanded="mobileOpen.toString()"
                    aria-label="Abrir menú móvil"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-glamora-charcoal/15 bg-white text-glamora-charcoal transition hover:border-glamora-terracotta lg:hidden"
                >
                    <svg x-show="!mobileOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    <svg x-show="mobileOpen" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Fila 2: Desktop Editorial Navigation (6 enlaces centrados) --}}
        <nav aria-label="Navegación principal" class="hidden lg:flex items-center justify-center gap-1.5 border-t border-glamora-blush/20 py-2">
            @foreach ($navItems as $item)
                @php
                    $isActive = request()->routeIs($item['pattern']);
                @endphp
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'whitespace-nowrap shrink-0 rounded-full px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.14em] transition',
                        'bg-glamora-charcoal text-glamora-cream shadow-xs' => $isActive,
                        'text-glamora-charcoal/85 hover:bg-white hover:text-glamora-terracotta' => ! $isActive,
                    ])
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>

    {{-- Mobile LiveSearch Drawer --}}
    <div
        x-show="mobileSearchOpen"
        x-cloak
        x-transition.opacity.duration.200ms
        class="border-t border-glamora-blush/30 bg-white px-4 py-3.5 max-h-[75vh] overflow-y-auto lg:hidden"
    >
        <livewire:storefront.live-search />
    </div>

    {{-- Mobile Dropdown Drawer --}}
    <div
        x-show="mobileOpen"
        x-cloak
        x-transition.opacity.duration.200ms
        class="border-t border-glamora-blush/30 bg-white px-4 py-4 max-h-[75vh] overflow-y-auto lg:hidden"
    >
        <nav aria-label="Navegación móvil" class="flex flex-col gap-1.5">
            @foreach ($navItems as $item)
                @php
                    $isActive = request()->routeIs($item['pattern']);
                @endphp
                <a
                    href="{{ route($item['route']) }}"
                    @click="mobileOpen = false"
                    @class([
                        'rounded-xl px-4 py-2.5 text-sm font-semibold transition',
                        'bg-glamora-charcoal text-glamora-cream' => $isActive,
                        'text-glamora-charcoal hover:bg-glamora-cream' => ! $isActive,
                    ])
                >
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if ($showCatalogPdf)
                <a
                    href="{{ route('catalog.pdf') }}"
                    target="_blank"
                    rel="noopener"
                    @click="mobileOpen = false"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold text-glamora-terracotta hover:bg-glamora-cream"
                >
                    Descargar Catálogo PDF
                </a>
            @endif

            <a
                href="{{ route('cart.index') }}"
                @click="mobileOpen = false"
                class="rounded-xl px-4 py-2.5 text-sm font-semibold text-glamora-charcoal hover:bg-glamora-cream"
            >
                Mi Bolsa y Cotización
            </a>

            <div class="mt-2 flex flex-wrap gap-2 border-t border-glamora-blush/25 pt-3">
                @auth
                    @if (Route::has('admin.dashboard') && auth()->user()->is_active && auth()->user()->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero', 'Vendedor B2B']))
                        <a href="{{ route('admin.dashboard') }}" class="w-full rounded-full border border-glamora-gold/60 bg-glamora-gold/15 px-4 py-2 text-center text-xs font-semibold text-glamora-charcoal transition hover:bg-glamora-gold/25">
                            Ir al Panel Admin
                        </a>
                    @endif
                    <a href="{{ route('account.index') }}" class="btn-glamora-outline flex-1 py-2 text-xs">
                        Mi Cuenta
                    </a>
                    <form action="{{ route('logout') }}" method="POST" class="flex-1">
                        @csrf
                        <button type="submit" class="btn-glamora-primary w-full py-2 text-xs">
                            Salir
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-glamora-outline flex-1 py-2 text-xs">
                        Ingresar
                    </a>
                    <a href="{{ route('register') }}" class="btn-glamora-primary flex-1 py-2 text-xs">
                        Crear Cuenta
                    </a>
                @endauth
            </div>
        </nav>
    </div>
</header>
