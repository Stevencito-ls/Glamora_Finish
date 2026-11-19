@php
    $navItems = [
        ['label' => 'Inicio', 'route' => 'home', 'pattern' => 'home'],
        ['label' => 'Catálogo', 'route' => 'catalog.index', 'pattern' => 'catalog.*'],
        ['label' => 'Marcas', 'route' => 'brands.index', 'pattern' => 'brands.*'],
        ['label' => 'Mayoristas', 'route' => 'wholesale.index', 'pattern' => 'wholesale.*'],
        ['label' => 'Blog', 'route' => 'blog.index', 'pattern' => 'blog.*'],
        ['label' => 'Seguimiento', 'route' => 'tracking.index', 'pattern' => 'tracking.*'],
    ];

    $brandName = $storeSettings?->store_name ?: 'Glamora Perú';
    $brandHeadline = mb_strtoupper($brandName, 'UTF-8');
    $brandTagline = $storeSettings?->store_tagline ?: 'Perú · Atelier & B2B';
    $brandInitial = mb_strtoupper(mb_substr(trim($brandName), 0, 1, 'UTF-8') ?: 'G', 'UTF-8');
    $logoUrl = $storeSettings?->logo_url;
@endphp

<header
    x-data="{ mobileOpen: false, searchOpen: false }"
    class="sticky top-0 z-50 border-b border-glamora-blush/30"
>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-glamora-cream/95 backdrop-blur-md"></div>

    <div class="relative mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
        {{-- Brand Logo --}}
        <a href="{{ route('home') }}" class="group flex shrink-0 items-center gap-3">
            @if ($logoUrl)
                <img
                    src="{{ $logoUrl }}"
                    alt="{{ $brandName }}"
                    class="h-10 w-10 rounded-full border border-glamora-gold/60 bg-white object-cover shadow-xs transition group-hover:scale-105"
                >
            @else
                <span class="flex h-10 w-10 items-center justify-center rounded-full border border-glamora-gold/60 bg-glamora-charcoal font-serif text-xl font-semibold text-glamora-gold transition group-hover:bg-glamora-terracotta group-hover:text-white">
                    {{ $brandInitial }}
                </span>
            @endif
            <div class="leading-none">
                <span class="block font-serif text-xl font-semibold tracking-[0.2em] text-glamora-charcoal sm:text-2xl">
                    {{ $brandHeadline }}
                </span>
                <span class="mt-0.5 block max-w-[190px] truncate text-[9px] font-semibold uppercase tracking-[0.22em] text-glamora-terracotta">
                    {{ $brandTagline }}
                </span>
            </div>
        </a>

        {{-- Desktop Nav --}}
        <nav aria-label="Navegación principal" class="hidden items-center gap-1 xl:flex">
            @foreach ($navItems as $item)
                @php
                    $isActive = request()->routeIs($item['pattern']);
                @endphp
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'rounded-full px-3.5 py-2 text-xs font-semibold uppercase tracking-[0.12em] transition',
                        'bg-glamora-charcoal text-glamora-cream shadow-xs' => $isActive,
                        'text-glamora-charcoal/80 hover:bg-white hover:text-glamora-terracotta' => ! $isActive,
                    ])
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Desktop Predictive Search --}}
        <div class="hidden min-w-0 flex-1 max-w-xs lg:block xl:max-w-sm">
            <livewire:storefront.live-search />
        </div>

        {{-- Right Utilities --}}
        <div class="flex shrink-0 items-center gap-2">
            <a
                href="{{ route('catalog.pdf') }}"
                title="Descargar Catálogo PDF"
                class="hidden items-center gap-1.5 rounded-full border border-glamora-charcoal/15 bg-white px-3 py-2 text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta hover:text-glamora-terracotta md:inline-flex"
            >
                <svg class="h-3.5 w-3.5 text-glamora-terracotta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0-3-3m3 3 3-3m2 8.25H7a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 7 3h5.586a1 1 0 0 1 .707.293l4.414 4.414a1 1 0 0 1 .293.707V19.5a2.25 2.25 0 0 1-2.25 2.25Z" />
                </svg>
                <span class="hidden 2xl:inline">Catálogo PDF</span>
                <span class="2xl:hidden">PDF</span>
            </a>

            @if (Route::has('admin.dashboard'))
                @auth
                    @if (auth()->user()->is_active && auth()->user()->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero', 'Vendedor B2B']))
                        <a
                            href="{{ route('admin.dashboard') }}"
                            class="hidden rounded-full border border-glamora-gold/50 bg-glamora-gold/10 px-3 py-2 text-xs font-semibold text-glamora-charcoal transition hover:bg-glamora-gold/20 lg:inline-flex"
                        >
                            Panel Admin
                        </a>
                    @endif
                @endauth
            @endif

            @auth
                <a
                    href="{{ route('account.index') }}"
                    class="hidden rounded-full border border-glamora-charcoal/15 bg-white px-3 py-2 text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta sm:inline-flex"
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
                    class="hidden rounded-full border border-glamora-charcoal/15 bg-white px-3 py-2 text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta sm:inline-flex"
                >
                    Ingresar
                </a>
                <a
                    href="{{ route('register') }}"
                    class="hidden rounded-full border border-glamora-terracotta/40 bg-glamora-terracotta/10 px-3 py-2 text-xs font-semibold text-glamora-terracotta transition hover:bg-glamora-terracotta hover:text-white 2xl:inline-flex"
                >
                    Crear Cuenta
                </a>
            @endauth

            {{-- Mobile Search Toggle --}}
            <button
                type="button"
                @click="searchOpen = !searchOpen"
                aria-label="Buscar productos"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-glamora-charcoal/15 bg-white text-glamora-charcoal transition hover:border-glamora-terracotta lg:hidden"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </button>

            <livewire:storefront.cart-counter-drawer />

            {{-- Mobile Menu Toggle --}}
            <button
                type="button"
                @click="mobileOpen = !mobileOpen"
                :aria-expanded="mobileOpen.toString()"
                aria-label="Abrir menú móvil"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-glamora-charcoal/15 bg-white text-glamora-charcoal transition hover:border-glamora-terracotta xl:hidden"
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

    {{-- Mobile Predictive Search Bar --}}
    <div
        x-show="searchOpen"
        x-cloak
        x-transition.opacity.duration.150ms
        class="border-t border-glamora-blush/30 bg-white px-4 py-3 lg:hidden"
    >
        <livewire:storefront.live-search />
    </div>

    {{-- Mobile Dropdown Drawer --}}
    <div
        x-show="mobileOpen"
        x-cloak
        x-transition.opacity.duration.200ms
        class="border-t border-glamora-blush/30 bg-white px-4 py-4 xl:hidden"
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

            <a
                href="{{ route('catalog.pdf') }}"
                @click="mobileOpen = false"
                class="rounded-xl px-4 py-2.5 text-sm font-semibold text-glamora-terracotta hover:bg-glamora-cream"
            >
                Descargar Catálogo PDF
            </a>

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
