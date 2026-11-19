<div class="relative" @keydown.escape.window="if ($wire.open) $wire.closeDrawer()">
    <button
        type="button"
        wire:click="openDrawer"
        aria-label="Abrir mi bolsa de compras"
        class="group relative inline-flex items-center gap-2.5 rounded-full border border-glamora-charcoal/15 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wider text-glamora-charcoal transition hover:border-glamora-terracotta hover:bg-glamora-cream"
    >
        <svg class="h-4 w-4 text-glamora-charcoal transition group-hover:text-glamora-terracotta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1.2 12.2a1.5 1.5 0 0 1-1.49 1.3H8.69a1.5 1.5 0 0 1-1.49-1.3L6 7Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V5a3 3 0 1 1 6 0v2" />
        </svg>
        <span class="hidden sm:inline">Bolsa</span>
        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-glamora-charcoal px-1.5 py-0.5 text-[11px] font-bold text-glamora-cream transition group-hover:bg-glamora-terracotta">
            {{ $itemsCount }}
        </span>
    </button>

    @if ($open)
        <div
            wire:key="cart-drawer-modal"
            class="fixed inset-0 z-[100] flex h-dvh w-screen justify-end"
            style="height: 100dvh;"
            role="dialog"
            aria-modal="true"
            aria-labelledby="glamora-cart-drawer-title"
        >
            {{-- Backdrop --}}
            <div
                wire:click="closeDrawer"
                class="fixed inset-0 bg-glamora-charcoal/50 backdrop-blur-xs transition-opacity"
            ></div>

            {{-- Slide-over Panel --}}
            <aside class="relative z-10 flex h-full max-h-dvh w-full max-w-md flex-col overflow-hidden bg-glamora-cream shadow-2xl">
                {{-- Header --}}
                <div class="flex shrink-0 items-center justify-between border-b border-glamora-blush/35 bg-white px-6 py-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-glamora-terracotta">
                            {{ $activeTier->label() }}
                        </p>
                        <h2 id="glamora-cart-drawer-title" class="font-serif text-2xl font-semibold text-glamora-charcoal">
                            Tu Bolsa Glamora ({{ $itemsCount }})
                        </h2>
                    </div>

                    <button
                        type="button"
                        wire:click="closeDrawer"
                        aria-label="Cerrar bolsa"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-glamora-charcoal/15 text-glamora-charcoal transition hover:bg-glamora-cream"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Free Shipping Progress Bar --}}
                @if ($itemsCount > 0)
                    <div class="shrink-0 border-b border-glamora-blush/30 bg-white/80 px-6 py-3">
                        <div class="flex items-center justify-between text-xs">
                            @if ($shippingQuote->qualifiesForFreeThreshold)
                                <span class="font-semibold text-emerald-700">
                                    &#10003; ¡Envío Estándar Gratis desbloqueado!
                                </span>
                            @else
                                <span class="text-glamora-charcoal">
                                    Faltan <strong class="text-glamora-terracotta">{{ $shippingQuote->remainingForFree->formatted }}</strong> para Envío Gratis
                                </span>
                            @endif
                            <span class="font-mono text-[11px] text-glamora-muted">
                                {{ number_format($shippingQuote->progressPercent, 0) }}%
                            </span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-glamora-cream">
                            <div
                                class="h-full rounded-full transition-all duration-300 {{ $shippingQuote->qualifiesForFreeThreshold ? 'bg-emerald-600' : 'bg-glamora-terracotta' }}"
                                style="width: {{ $shippingQuote->progressPercent }}%"
                            ></div>
                        </div>
                    </div>
                @endif

                {{-- Body --}}
                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    @if (count($items) === 0)
                        <div class="flex h-full flex-col items-center justify-center py-12 text-center">
                            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full border border-glamora-blush/50 bg-white text-glamora-terracotta">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1.2 12.2a1.5 1.5 0 0 1-1.49 1.3H8.69a1.5 1.5 0 0 1-1.49-1.3L6 7Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V5a3 3 0 1 1 6 0v2" />
                                </svg>
                            </div>
                            <h3 class="font-serif text-2xl font-semibold text-glamora-charcoal">Tu bolsa está vacía</h3>
                            <p class="mt-2 max-w-xs text-sm text-glamora-muted">
                                Descubre maquillaje, cuidado de la piel y packs para tu negocio con envío a todo el Perú.
                            </p>
                            <a
                                href="{{ route('catalog.index') }}"
                                wire:click="closeDrawer"
                                class="btn-glamora-primary mt-6"
                            >
                                Explorar Catálogo
                            </a>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($items as $item)
                                @php
                                    $variantId = (int) $item['product_variant_id'];
                                    $qty = (int) $item['quantity'];
                                    $maxStock = (int) ($item['available_stock'] ?? 99);
                                    $img = \App\Support\CosmeticImageResolver::resolve(
                                        $item['image_path'] ?? null,
                                        'variant',
                                        $item['sku'] ?? ($item['product_slug'] ?? null)
                                    );
                                    $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($item['hex_color'] ?? '')) === 1
                                        ? (string) $item['hex_color']
                                        : '#C87D72';
                                @endphp

                                <div
                                    wire:key="drawer-item-{{ $variantId }}"
                                    class="flex gap-4 rounded-2xl border border-glamora-blush/35 bg-white p-3.5 shadow-xs"
                                >
                                    <a href="{{ route('product.show', $item['product_slug']) }}" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-glamora-blush/30 bg-glamora-cream">
                                        <img src="{{ $img }}" alt="{{ $item['product_name'] }}" class="h-full w-full object-cover">
                                    </a>

                                    <div class="flex min-w-0 flex-1 flex-col justify-between">
                                        <div>
                                            <div class="flex items-start justify-between gap-2">
                                                <a
                                                    href="{{ route('product.show', $item['product_slug']) }}"
                                                    class="truncate text-sm font-semibold text-glamora-charcoal hover:text-glamora-terracotta"
                                                >
                                                    {{ $item['product_name'] }}
                                                </a>
                                                <button
                                                    type="button"
                                                    wire:click="removeItem({{ $variantId }})"
                                                    aria-label="Quitar {{ $item['product_name'] }}"
                                                    class="text-glamora-muted transition hover:text-rose-600"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <div class="mt-1 flex items-center gap-2 text-xs text-glamora-muted">
                                                <span
                                                    class="inline-block h-3 w-3 rounded-full border border-black/15"
                                                    style="background-color: {{ $safeHex }}"
                                                ></span>
                                                <span class="truncate">{{ $item['shade_name'] }}</span>
                                                <span class="font-mono text-[10px] text-glamora-muted/80">· {{ $item['sku'] }}</span>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center justify-between">
                                            <div class="inline-flex items-center rounded-full border border-glamora-charcoal/15 bg-glamora-cream">
                                                <button
                                                    type="button"
                                                    wire:click="decrement({{ $variantId }})"
                                                    aria-label="Disminuir cantidad"
                                                    class="px-2.5 py-1 text-xs font-bold text-glamora-charcoal hover:text-glamora-terracotta"
                                                >
                                                    &minus;
                                                </button>
                                                <span class="min-w-6 text-center text-xs font-semibold text-glamora-charcoal">
                                                    {{ $qty }}
                                                </span>
                                                <button
                                                    type="button"
                                                    wire:click="increment({{ $variantId }})"
                                                    @disabled($qty >= $maxStock)
                                                    aria-label="Aumentar cantidad"
                                                    class="px-2.5 py-1 text-xs font-bold text-glamora-charcoal hover:text-glamora-terracotta disabled:opacity-35"
                                                >
                                                    +
                                                </button>
                                            </div>

                                            <div class="text-right">
                                                <p class="text-sm font-bold text-glamora-charcoal">
                                                    S/ {{ number_format((float) $item['subtotal'], 2) }}
                                                </p>
                                                <p class="text-[11px] text-glamora-muted">
                                                    S/ {{ number_format((float) $item['unit_price'], 2) }} c/u
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Completa tu Rutina (Venta Cruzada) --}}
                    @if (isset($crossSellProducts) && $crossSellProducts->isNotEmpty())
                        <div class="mt-6 border-t border-glamora-blush/35 pt-5">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-glamora-terracotta">
                                Completa tu Rutina
                            </p>
                            <div class="mt-3 space-y-2.5">
                                @foreach ($crossSellProducts->take(3) as $csProduct)
                                    @php
                                        $csVariant = $csProduct->variants->firstWhere('is_default', true) ?? $csProduct->variants->first();
                                        $csPrice = $csVariant ? $csVariant->priceForTier($activeTier, 1) : 0.0;
                                        $csStock = $csVariant ? $csVariant->availableStock() : 0;
                                    @endphp
                                    @if ($csVariant && $csStock > 0)
                                        <div
                                            wire:key="drawer-cs-{{ $csProduct->id }}"
                                            class="flex items-center justify-between gap-3 rounded-xl border border-glamora-blush/30 bg-white p-2.5 shadow-2xs"
                                        >
                                            <a
                                                href="{{ route('product.show', $csProduct->slug) }}"
                                                wire:click="closeDrawer"
                                                class="flex min-w-0 flex-1 items-center gap-2.5"
                                            >
                                                <img
                                                    src="{{ $csVariant->image_url ?: $csProduct->image_url }}"
                                                    alt="{{ $csProduct->name }}"
                                                    class="h-11 w-11 shrink-0 rounded-lg border border-glamora-blush/25 bg-glamora-cream object-cover"
                                                >
                                                <div class="min-w-0">
                                                    <p class="truncate text-xs font-semibold text-glamora-charcoal">
                                                        {{ $csProduct->name }}
                                                    </p>
                                                    <p class="truncate text-[11px] text-glamora-muted">
                                                        {{ $csVariant->shade_name }} · <strong class="text-glamora-charcoal">S/ {{ number_format($csPrice, 2) }}</strong>
                                                    </p>
                                                </div>
                                            </a>
                                            <button
                                                type="button"
                                                wire:click="quickAddCrossSell({{ $csVariant->id }})"
                                                class="shrink-0 rounded-full bg-glamora-terracotta px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-glamora-charcoal"
                                            >
                                                + Bolsa
                                            </button>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                @if (count($items) > 0)
                    <div class="shrink-0 border-t border-glamora-blush/35 bg-white px-6 py-5">
                        @if ($couponCode && $couponDiscount > 0)
                            <div class="mb-1.5 flex items-center justify-between text-xs font-medium text-emerald-700">
                                <span>Cupón ({{ $couponCode }})</span>
                                <span>-S/ {{ number_format($couponDiscount, 2) }}</span>
                            </div>
                        @endif

                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="text-glamora-muted">Subtotal productos (incl. IGV)</span>
                            <span class="font-serif text-2xl font-semibold text-glamora-charcoal">
                                S/ {{ number_format($subtotal, 2) }}
                            </span>
                        </div>
                        <p class="mb-4 text-xs text-glamora-muted">
                            Precios con tu descuento {{ $activeTier->label() }} incluido.
                        </p>

                        <div class="flex flex-col gap-2.5">
                            <a href="{{ route('checkout.index') }}" class="btn-glamora-primary w-full justify-center">
                                Finalizar Pedido &rarr;
                            </a>
                            <a href="{{ route('cart.index') }}" class="btn-glamora-outline w-full justify-center py-2.5 text-xs uppercase tracking-wider">
                                VER BOLSA Y COTIZAR PDF
                            </a>

                            <div class="flex items-center justify-between pt-1">
                                <button
                                    type="button"
                                    wire:click="clearCart"
                                    class="text-xs font-medium text-glamora-muted underline-offset-4 hover:text-rose-700 hover:underline"
                                >
                                    Vaciar bolsa
                                </button>
                                <a
                                    href="{{ route('wholesale.index') }}"
                                    wire:click="closeDrawer"
                                    class="text-xs font-semibold text-glamora-terracotta hover:underline"
                                >
                                    ¿Tienes un negocio o salón? Precios por mayor &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    @endif
</div>
