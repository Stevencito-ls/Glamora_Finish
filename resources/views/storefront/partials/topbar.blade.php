<div class="bg-glamora-charcoal text-glamora-cream">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-[11px] tracking-wider sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="inline-flex items-center gap-1.5 font-medium text-glamora-gold">
                <span class="h-1.5 w-1.5 rounded-full bg-glamora-gold"></span>
                DIGEMID Verificado
            </span>
            <span class="hidden text-glamora-cream/80 md:inline">
                Trazabilidad FEFO por lote · Envío Gratis desde S/ {{ number_format(\App\Services\ShippingService::FREE_SHIPPING_THRESHOLD, 2) }}
            </span>
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
