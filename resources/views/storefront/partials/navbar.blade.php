@php
    $navItems = [
        ['label' => 'Inicio', 'route' => 'home', 'pattern' => 'home'],
        ['label' => 'Catálogo', 'route' => 'catalog.index', 'pattern' => 'catalog.*'],
        ['label' => 'Marcas', 'route' => 'brands.index', 'pattern' => 'brands.*'],
        ['label' => 'Mayoristas', 'route' => 'wholesale.index', 'pattern' => 'wholesale.*'],
        ['label' => 'Blog', 'route' => 'blog.index', 'pattern' => 'blog.*'],
        ['label' => 'Seguimiento', 'route' => 'tracking.index', 'pattern' => 'tracking.*'],
    ];
@endphp

<header
    x-data="{ mobileOpen: false }"
    class="sticky top-0 z-50 border-b border-glamora-blush/30"
>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-glamora-cream/95 backdrop-blur-md"></div>

    <div class="relative mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
        {{-- Brand Logo --}}
        <a href="{{ route('home') }}" class="group flex shrink-0 items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full border border-glamora-gold/60 bg-glamora-charcoal font-serif text-xl font-semibold text-glamora-gold transition group-hover:bg-glamora-terracotta group-hover:text-white">
                G
            </span>
            <div class="leading-none">
                <span class="block font-serif text-2xl font-semibold tracking-[0.2em] text-glamora-charcoal">
                    GLAMORA
                </span>
                <span class="mt-0.5 block text-[9px] font-semibold uppercase tracking-[0.28em] text-glamora-terracotta">
                    Perú · Atelier &amp; B2B
                </span>
            </div>
        </a>

        {{-- Desktop Nav --}}
        <nav aria-label="Navegación principal" class="hidden items-center gap-1 lg:flex">
            @foreach ($navItems as $item)
                @php
                    $isActive = request()->routeIs($item['pattern']);
                @endphp
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] transition',
                        'bg-glamora-charcoal text-glamora-cream shadow-xs' => $isActive,
                        'text-glamora-charcoal/80 hover:bg-white hover:text-glamora-terracotta' => ! $isActive,
                    ])
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Right Utilities --}}
        <div class="flex shrink-0 items-center gap-2.5">
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

            {{-- Mobile Menu Toggle --}}
            <button
                type="button"
                @click="mobileOpen = !mobileOpen"
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

    {{-- Mobile Dropdown Drawer --}}
    <div
        x-show="mobileOpen"
        x-cloak
        x-transition.opacity.duration.200ms
        class="border-t border-glamora-blush/30 bg-white px-4 py-4 lg:hidden"
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
