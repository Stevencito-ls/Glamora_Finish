@props([
    'product',
    'pricingTier' => \App\Enums\CustomerTier::Minorista,
    'useLivewireAdd' => false,
])

@php
    $activeVariants = $product->variants->where('is_active', true)->values();
    $defaultVariant = $activeVariants->firstWhere('is_default', true) ?? $activeVariants->first();

    $variantPayloads = $activeVariants->map(function (\App\Models\ProductVariant $variant) use ($pricingTier, $product): array {
        if (! $variant->relationLoaded('product')) {
            $variant->setRelation('product', $product);
        }
        $stock = $variant->availableStock();
        $tierPrice = $variant->priceForTier($pricingTier, 1);
        $b2bMinPrice = $variant->priceForTier(\App\Enums\CustomerTier::Oro, 12);

        $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $variant->hex_color) === 1
            ? (string) $variant->hex_color
            : '#C87D72';

        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'shade_name' => $variant->shade_name,
            'hex_color' => $safeHex,
            'image_url' => $variant->image_url,
            'base_price' => (float) $variant->base_price,
            'tier_price' => $tierPrice,
            'wholesale_price' => (float) ($variant->wholesale_base_price ?: $b2bMinPrice),
            'stock' => $stock,
        ];
    })->values()->all();

    $totalStock = (int) $activeVariants->sum(fn (\App\Models\ProductVariant $v): int => $v->availableStock());
    $mainImg = $product->image_url;
@endphp

<article
    x-data="{
        variants: @js($variantPayloads),
        mainImage: @js($mainImg),
        selectedIndex: 0,
        get current() {
            return this.variants[this.selectedIndex] ?? {
                id: 0,
                sku: '',
                shade_name: '',
                hex_color: '#C87D72',
                image_url: this.mainImage,
                base_price: 0,
                tier_price: 0,
                wholesale_price: 0,
                stock: 0
            };
        }
    }"
    class="card-glamora-hover group flex h-full flex-col overflow-hidden"
>
    {{-- Media Frame --}}
    <div class="relative aspect-square overflow-hidden bg-glamora-cream">
        <a href="{{ route('product.show', $product->slug) }}" class="block h-full w-full">
            <img
                :src="current.image_url"
                alt="{{ $product->name }}"
                loading="lazy"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
            >
        </a>

        {{-- Top Badges --}}
        <div class="pointer-events-none absolute top-3 left-3 flex flex-wrap gap-1.5">
            @if ($product->is_b2b_kit)
                <span class="rounded-full bg-glamora-charcoal px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-glamora-gold shadow-xs">
                    Kit B2B
                </span>
            @endif
            @if ($product->is_new_launch)
                <span class="rounded-full bg-glamora-terracotta px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white shadow-xs">
                    Nuevo
                </span>
            @endif
        </div>

        {{-- Stock Pill --}}
        <div class="pointer-events-none absolute top-3 right-3">
            @if ($totalStock > 0)
                <span class="rounded-full border border-emerald-200 bg-white/90 px-2.5 py-1 text-[10px] font-semibold text-emerald-800 backdrop-blur-xs">
                    Disponible · {{ $totalStock }} uds.
                </span>
            @else
                <span class="rounded-full border border-rose-200 bg-white/90 px-2.5 py-1 text-[10px] font-semibold text-rose-700">
                    Agotado
                </span>
            @endif
        </div>
    </div>

    {{-- Card Body --}}
    <div class="flex flex-1 flex-col p-5">
        <div class="flex items-center justify-between gap-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-glamora-terracotta">
            <span class="truncate">{{ $product->brand?->name }}</span>
            <span class="shrink-0 font-normal text-glamora-muted">{{ $product->category?->name }}</span>
        </div>

        <a href="{{ route('product.show', $product->slug) }}" class="mt-1.5 block">
            <h3 class="font-serif text-xl font-semibold leading-snug text-glamora-charcoal transition group-hover:text-glamora-terracotta">
                {{ $product->name }}
            </h3>
        </a>

        <p class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-glamora-muted">
            {{ $product->short_description }}
        </p>

        {{-- Interactive Hex Swatches --}}
        @if ($activeVariants->count() > 0)
            <div class="mt-4">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($activeVariants as $idx => $variant)
                        @php
                            $swatchHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $variant->hex_color) === 1
                                ? (string) $variant->hex_color
                                : '#C87D72';
                        @endphp
                        <button
                            @if ($useLivewireAdd) wire:key="swatch-{{ $product->id }}-{{ $variant->id }}" @endif
                            type="button"
                            @click="selectedIndex = {{ $idx }}"
                            :class="selectedIndex === {{ $idx }} ? 'swatch-ring-active' : ''"
                            class="swatch-ring h-6 w-6"
                            style="background-color: {{ $swatchHex }}"
                            title="{{ $variant->shade_name }} ({{ $variant->sku }})"
                            aria-label="Seleccionar tono {{ $variant->shade_name }}"
                        ></button>
                    @endforeach
                </div>
                <p class="mt-2 truncate text-xs text-glamora-muted">
                    Tono: <span class="font-medium text-glamora-charcoal" x-text="current.shade_name"></span>
                    <span class="font-mono text-[10px] text-glamora-muted/80" x-text="'· ' + current.sku"></span>
                </p>
            </div>
        @endif

        {{-- Pricing & Actions --}}
        <div class="mt-auto pt-4">
            <div class="flex items-end justify-between border-t border-glamora-blush/25 pt-3.5">
                <div>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-glamora-muted">
                        {{ $pricingTier === \App\Enums\CustomerTier::Minorista ? 'Precio Lista' : $pricingTier->label() }}
                    </span>
                    <div class="flex items-baseline gap-2">
                        <span class="font-serif text-2xl font-bold text-glamora-charcoal" x-text="'S/ ' + Number(current.tier_price).toFixed(2)">
                            S/ {{ number_format($defaultVariant ? $defaultVariant->priceForTier($pricingTier, 1) : 0, 2) }}
                        </span>
                        <template x-if="current.tier_price < current.base_price">
                            <span class="text-xs text-glamora-muted line-through" x-text="'S/ ' + Number(current.base_price).toFixed(2)"></span>
                        </template>
                    </div>
                </div>

                <div class="text-right">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-glamora-terracotta">
                        Mayorista desde
                    </span>
                    <span class="text-xs font-bold text-glamora-charcoal" x-text="'S/ ' + Number(current.wholesale_price).toFixed(2)">
                        S/ {{ number_format($defaultVariant ? (float) $defaultVariant->wholesale_base_price : 0, 2) }}
                    </span>
                </div>
            </div>

            <div class="mt-3.5 flex items-center gap-2">
                <a
                    href="{{ route('product.show', $product->slug) }}"
                    class="flex-1 rounded-full border border-glamora-charcoal/20 py-2 text-center text-xs font-semibold text-glamora-charcoal transition hover:border-glamora-charcoal hover:bg-glamora-charcoal hover:text-glamora-cream"
                >
                    Ver detalle
                </a>

                @if ($useLivewireAdd)
                    <button
                        type="button"
                        @click="$wire.quickAdd(current.id)"
                        :disabled="current.stock <= 0"
                        class="flex-1 rounded-full bg-glamora-terracotta py-2 text-center text-xs font-semibold text-white transition hover:bg-glamora-charcoal disabled:cursor-not-allowed disabled:opacity-45"
                    >
                        + Agregar
                    </button>
                @else
                    <form action="{{ route('cart.add') }}" method="POST" class="flex-1">
                        @csrf
                        <input type="hidden" name="variant_id" :value="current.id">
                        <input type="hidden" name="quantity" value="1">
                        <button
                            type="submit"
                            :disabled="current.stock <= 0"
                            class="w-full rounded-full bg-glamora-terracotta py-2 text-center text-xs font-semibold text-white transition hover:bg-glamora-charcoal disabled:cursor-not-allowed disabled:opacity-45"
                        >
                            + Agregar
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</article>
