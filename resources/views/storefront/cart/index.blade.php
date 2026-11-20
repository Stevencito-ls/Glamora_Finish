@extends('layouts.storefront')

@section('title', 'Mi Bolsa de Compras — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <section class="border-b border-glamora-blush/35 bg-gradient-to-r from-[#FAF6F2] via-white to-[#F7ECE4]">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="badge-glamora-gold">{{ $activeTier->label() }}</span>
                    <h1 class="mt-3 font-serif text-4xl font-semibold text-glamora-charcoal sm:text-5xl">
                        Mi Bolsa de Compras
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-glamora-muted">
                        Tus descuentos por cantidad o nivel de cliente se aplican automáticamente. Productos 100% originales con fecha de vencimiento garantizada.
                    </p>
                </div>

                @if ($itemsCount > 0)
                    <a
                        href="{{ route('cart.quote-pdf') }}"
                        class="btn-glamora-outline px-5 py-2.5 text-xs uppercase tracking-wider"
                    >
                        <svg class="h-4 w-4 text-glamora-terracotta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0-3-3m3 3 3-3m2 8.25H7a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 7 3h5.586a1 1 0 0 1 .707.293l4.414 4.414a1 1 0 0 1 .293.707V19.5a2.25 2.25 0 0 1-2.25 2.25Z" />
                        </svg>
                        Descargar Cotización PDF
                    </a>
                @endif
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if (count($items) === 0)
            <div class="card-glamora mx-auto max-w-2xl p-10 text-center">
                <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full border border-glamora-blush/50 bg-glamora-cream text-glamora-terracotta">
                    <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1.2 12.2a1.5 1.5 0 0 1-1.49 1.3H8.69a1.5 1.5 0 0 1-1.49-1.3L6 7Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V5a3 3 0 1 1 6 0v2" />
                    </svg>
                </div>
                <h2 class="font-serif text-3xl font-semibold text-glamora-charcoal">Tu bolsa Glamora está vacía</h2>
                <p class="mt-3 text-sm text-glamora-muted">
                    Explora nuestra colección de maquillaje editorial, skincare botánico y kits mayoristas B2B con registro NSOC DIGEMID.
                </p>
                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('catalog.index') }}" class="btn-glamora-primary">
                        Explorar Catálogo
                    </a>
                    <a href="{{ route('wholesale.index') }}" class="btn-glamora-outline">
                        Portal Mayoristas B2B
                    </a>
                </div>
            </div>
        @else
            {{-- Free Shipping Progress Bar --}}
            <div class="card-glamora mb-8 p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    @if ($shippingQuote->qualifiesForFreeThreshold)
                        <p class="flex items-center gap-2 font-semibold text-emerald-800">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">&#10003;</span>
                            ¡Felicidades! Tu pedido califica para <strong>Envío Estándar Gratis</strong> en Lima y Provincias.
                        </p>
                    @else
                        <p class="text-glamora-charcoal">
                            Te faltan <strong class="text-glamora-terracotta">{{ $shippingQuote->remainingForFree->formatted }}</strong> para desbloquear <strong>Envío Estándar Gratis</strong> (desde {{ $shippingQuote->threshold->formatted }}).
                        </p>
                    @endif
                    <span class="font-mono text-xs font-semibold text-glamora-muted">
                        {{ number_format($shippingQuote->progressPercent, 0) }}% completado
                    </span>
                </div>
                <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-glamora-cream">
                    <div
                        class="h-full rounded-full transition-all duration-500 {{ $shippingQuote->qualifiesForFreeThreshold ? 'bg-emerald-600' : 'bg-gradient-to-r from-glamora-terracotta to-glamora-gold' }}"
                        style="width: {{ $shippingQuote->progressPercent }}%"
                    ></div>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-12">
                {{-- Left Column: Cart Table & Coupon --}}
                <div class="space-y-6 lg:col-span-8">
                    <div class="card-glamora overflow-hidden">
                        <div class="border-b border-glamora-blush/35 bg-white px-6 py-4">
                            <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                                Productos en tu bolsa ({{ $itemsCount }} {{ $itemsCount === 1 ? 'unidad' : 'unidades' }})
                            </h2>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left">
                                <thead>
                                    <tr class="border-b border-glamora-blush/30 bg-glamora-cream/70 text-[11px] font-bold uppercase tracking-wider text-glamora-muted">
                                        <th class="px-6 py-3.5">Producto &amp; Tono</th>
                                        <th class="px-4 py-3.5 text-right">Precio Unit.</th>
                                        <th class="px-4 py-3.5 text-center">Cantidad</th>
                                        <th class="px-4 py-3.5 text-right">Subtotal</th>
                                        <th class="px-4 py-3.5 text-right">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-glamora-blush/25 bg-white">
                                    @foreach ($items as $item)
                                        @php
                                            $variantId = (int) $item['product_variant_id'];
                                            $qty = (int) $item['quantity'];
                                            $maxStock = max($qty, (int) ($item['available_stock'] ?? 99));
                                            $basePrice = (float) ($item['base_price'] ?? $item['unit_price']);
                                            $unitPrice = (float) $item['unit_price'];
                                            $img = \App\Support\CosmeticImageResolver::resolve(
                                                $item['image_path'] ?? null,
                                                'variant',
                                                $item['sku'] ?? ($item['product_slug'] ?? null)
                                            );
                                            $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($item['hex_color'] ?? '')) === 1
                                                ? (string) $item['hex_color']
                                                : '#C87D72';
                                        @endphp
                                        <tr class="align-middle">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-4">
                                                    <a href="{{ route('product.show', $item['product_slug']) }}" class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-glamora-blush/35 bg-glamora-cream">
                                                        <img src="{{ $img }}" alt="{{ $item['product_name'] }}" class="h-full w-full object-cover">
                                                    </a>
                                                    <div class="min-w-0">
                                                        <p class="text-[10px] font-bold uppercase tracking-widest text-glamora-terracotta">
                                                            {{ $item['brand_name'] ?? 'Glamora' }}
                                                        </p>
                                                        <a href="{{ route('product.show', $item['product_slug']) }}" class="block font-semibold text-glamora-charcoal hover:text-glamora-terracotta">
                                                            {{ $item['product_name'] }}
                                                        </a>
                                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-glamora-muted">
                                                            {{-- Hexagonal Swatch --}}
                                                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                                                <polygon points="12,1.5 21,6.5 21,17.5 12,22.5 3,17.5 3,6.5" fill="{{ $safeHex }}" stroke="#2B2523" stroke-opacity="0.22" stroke-width="1.2" />
                                                            </svg>
                                                            <span>{{ $item['shade_name'] }}</span>
                                                            <span class="font-mono text-[11px] text-glamora-muted/80">· {{ $item['sku'] }}</span>
                                                        </div>
                                                        <p class="mt-0.5 text-[11px] text-emerald-700">
                                                            Disponible: {{ $maxStock }} uds.
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-4 py-4 text-right">
                                                <p class="text-sm font-bold text-glamora-charcoal">
                                                    S/ {{ number_format($unitPrice, 2) }}
                                                </p>
                                                @if ($basePrice > $unitPrice)
                                                    <p class="text-xs text-glamora-muted line-through">
                                                        S/ {{ number_format($basePrice, 2) }}
                                                    </p>
                                                    <span class="inline-block rounded-full bg-glamora-blush/25 px-2 py-0.5 text-[10px] font-semibold text-glamora-terracotta">
                                                        Descuento aplicado
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="px-4 py-4">
                                                <form action="{{ route('cart.update') }}" method="POST" class="flex items-center justify-center gap-1.5">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="variant_id" value="{{ $variantId }}">
                                                    <input
                                                        type="number"
                                                        name="quantity"
                                                        value="{{ $qty }}"
                                                        min="1"
                                                        max="{{ $maxStock }}"
                                                        aria-label="Cantidad para {{ $item['product_name'] }}"
                                                        class="w-18 rounded-xl border border-glamora-charcoal/20 bg-glamora-cream px-2.5 py-1.5 text-center text-sm font-semibold text-glamora-charcoal focus:border-glamora-terracotta focus:outline-none"
                                                    >
                                                    <button
                                                        type="submit"
                                                        class="rounded-xl border border-glamora-charcoal/15 bg-white px-2.5 py-1.5 text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-terracotta hover:text-glamora-terracotta"
                                                    >
                                                        Aplicar
                                                    </button>
                                                </form>
                                            </td>

                                            <td class="px-4 py-4 text-right font-serif text-lg font-bold text-glamora-charcoal">
                                                S/ {{ number_format((float) $item['subtotal'], 2) }}
                                            </td>

                                            <td class="px-4 py-4 text-right">
                                                <form action="{{ route('cart.remove', $variantId) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        aria-label="Eliminar {{ $item['product_name'] }}"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-600 hover:text-white"
                                                    >
                                                        &times;
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Coupon Form --}}
                    <div class="card-glamora p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                                    Cupón Promocional
                                </h3>
                                <p class="mt-1 text-xs text-glamora-muted">
                                    Cupones vigentes: <span class="font-mono font-semibold text-glamora-charcoal">GLAMORA10</span> (10% desde S/ 120),
                                    <span class="font-mono font-semibold text-glamora-charcoal">BIENVENIDA15</span> (15% minorista desde S/ 100),
                                    <span class="font-mono font-semibold text-glamora-charcoal">ORO50</span> (S/ 50 Mayorista Oro desde S/ 800).
                                </p>
                            </div>

                            @if ($couponCode)
                                <form action="{{ route('cart.coupon.remove') }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50 px-3.5 py-1 text-xs font-semibold text-emerald-800 transition hover:bg-rose-50 hover:text-rose-700"
                                    >
                                        <span>{{ $couponCode }} aplicado</span>
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </form>
                            @endif
                        </div>

                        <form action="{{ route('cart.coupon.apply') }}" method="POST" class="mt-4 flex flex-col gap-3 sm:flex-row">
                            @csrf
                            <div class="flex-1">
                                <label for="coupon-code-input" class="sr-only">Código de cupón</label>
                                <input
                                    id="coupon-code-input"
                                    type="text"
                                    name="code"
                                    value="{{ old('code', $couponCode) }}"
                                    placeholder="Ingresa tu cupón (ej. GLAMORA10)"
                                    class="input-glamora font-mono uppercase"
                                >
                                @error('code')
                                    <p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit" class="btn-glamora-outline px-6 py-2.5 text-xs uppercase tracking-wider">
                                Aplicar Cupón
                            </button>
                        </form>

                        @if ($couponWarning)
                            <p class="mt-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-xs font-medium text-amber-900">
                                {{ $couponWarning }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Right Column: Summary & IGV Breakdown --}}
                <aside class="lg:col-span-4">
                    <div class="card-glamora sticky top-24 p-6">
                        <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                            Resumen del Pedido
                        </h2>

                        <dl class="mt-5 space-y-3 border-b border-glamora-blush/30 pb-5 text-sm">
                            <div class="flex justify-between text-glamora-muted">
                                <dt>Valor lista catálogo</dt>
                                <dd>S/ {{ number_format($listSubtotal, 2) }}</dd>
                            </div>

                            @if ($tierSavings > 0)
                                <div class="flex justify-between font-medium text-glamora-terracotta">
                                    <dt>Tu ahorro por cantidad</dt>
                                    <dd>-S/ {{ number_format($tierSavings, 2) }}</dd>
                                </div>
                            @endif

                            <div class="flex justify-between font-semibold text-glamora-charcoal">
                                <dt>Subtotal productos</dt>
                                <dd>S/ {{ number_format($subtotal, 2) }}</dd>
                            </div>

                            @if ($couponDiscount > 0)
                                <div class="flex justify-between font-medium text-emerald-700">
                                    <dt>Descuento cupón ({{ $couponCode }})</dt>
                                    <dd>-S/ {{ number_format($couponDiscount, 2) }}</dd>
                                </div>
                            @endif

                            <div class="flex justify-between text-xs text-glamora-muted">
                                <dt>Op. Gravada (Base Imponible)</dt>
                                <dd>S/ {{ number_format($taxableBase, 2) }}</dd>
                            </div>

                            <div class="flex justify-between text-xs text-glamora-muted">
                                <dt>IGV (18% incluido Perú)</dt>
                                <dd>S/ {{ number_format($taxIgv, 2) }}</dd>
                            </div>

                            <div class="flex justify-between text-glamora-charcoal">
                                <dt>Envío ({{ $shippingQuote->method->label() }})</dt>
                                <dd class="font-semibold {{ $shippingQuote->isFree ? 'text-emerald-700' : '' }}">
                                    {{ $shippingQuote->isFree ? 'GRATIS' : $shippingQuote->cost->formatted }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-5 flex items-baseline justify-between">
                            <span class="text-sm font-semibold uppercase tracking-wider text-glamora-muted">Total Estimado</span>
                            <span class="font-serif text-3xl font-bold text-glamora-charcoal">
                                S/ {{ number_format($total, 2) }}
                            </span>
                        </div>

                        <div class="mt-6 space-y-3">
                            <a href="{{ route('checkout.index') }}" class="btn-glamora-primary w-full justify-center">
                                Proceder al Checkout &rarr;
                            </a>

                            <a href="{{ route('cart.quote-pdf') }}" class="btn-glamora-outline w-full justify-center text-xs uppercase tracking-wider">
                                Descargar Cotización PDF
                            </a>

                            @if ($whatsappCheckoutEnabled ?? true)
                                <a
                                    href="{{ $whatsappCartUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-[#25D366]/50 bg-[#25D366]/10 px-5 py-2.5 text-xs font-semibold text-[#128C7E] transition hover:bg-[#25D366] hover:text-white"
                                >
                                    Enviar Bolsa por WhatsApp
                                </a>
                            @endif

                            <a href="{{ route('catalog.index') }}" class="block pt-2 text-center text-xs font-semibold text-glamora-muted hover:text-glamora-terracotta">
                                &larr; Seguir explorando el catálogo
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        @endif

        {{-- Completa tu Rutina (Cross-Sell) --}}
        @if (isset($recommendations) && $recommendations->isNotEmpty())
            <section class="mt-14 border-t border-glamora-blush/35 pt-10">
                <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-[0.2em] text-glamora-terracotta">Recomendado para tu bolsa</span>
                        <h2 class="mt-1 font-serif text-3xl font-semibold text-glamora-charcoal">
                            Completa tu Rutina
                        </h2>
                    </div>
                    <a href="{{ route('catalog.index') }}" class="text-xs font-semibold uppercase tracking-wider text-glamora-charcoal hover:text-glamora-terracotta">
                        Ver todo el catálogo &rarr;
                    </a>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recommendations as $rec)
                        @include('storefront.partials.product-card', [
                            'product' => $rec,
                            'pricingTier' => $activeTier,
                            'badges' => $productBadges[$rec->id] ?? [],
                        ])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
