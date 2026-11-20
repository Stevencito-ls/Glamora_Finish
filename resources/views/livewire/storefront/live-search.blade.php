<div
    class="relative"
    x-data
    @click.outside="$wire.closeSearch()"
    @keydown.escape.window="$wire.closeSearch()"
>
    <form wire:submit="submitSearch" class="relative flex items-center">
        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-glamora-muted">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
            </svg>
        </span>

        <input
            type="search"
            wire:model.live.debounce.250ms="query"
            @focus="if ($wire.query.trim().length >= 2) $wire.openSearch()"
            placeholder="Buscar productos, tonos, marcas o SKU..."
            aria-label="Buscar productos"
            class="w-full rounded-full border border-glamora-charcoal/15 bg-white py-2 pr-9 pl-10 text-xs text-glamora-charcoal placeholder:text-glamora-muted/70 transition focus:border-glamora-terracotta focus:outline-none focus:ring-2 focus:ring-glamora-terracotta/20"
        >

        @if ($query !== '')
            <button
                type="button"
                wire:click="clearSearch"
                aria-label="Limpiar búsqueda"
                class="absolute inset-y-0 right-3 flex items-center text-sm font-bold text-glamora-muted transition hover:text-glamora-charcoal"
            >
                &times;
            </button>
        @endif
    </form>

    {{-- Live Search Dropdown --}}
    @if ($open && mb_strlen($term) >= 2)
        <div class="absolute top-full left-0 right-0 z-[65] mt-2 max-h-[65vh] overflow-y-auto rounded-2xl border border-glamora-blush/40 bg-white p-3 shadow-2xl">
            {{-- Skeleton Loading State --}}
            <div wire:loading.block wire:target="query" class="space-y-2.5 py-1">
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex items-center gap-3 rounded-xl p-2">
                        <div class="skeleton-glamora h-12 w-12 shrink-0 rounded-xl"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="skeleton-glamora h-3.5 w-3/4 rounded-full"></div>
                            <div class="skeleton-glamora h-3 w-1/2 rounded-full"></div>
                        </div>
                        <div class="skeleton-glamora h-7 w-16 shrink-0 rounded-full"></div>
                    </div>
                @endfor
            </div>

            {{-- Results --}}
            <div wire:loading.remove wire:target="query">
                @forelse ($results as $product)
                    @php
                        $defaultVariant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
                        $variantStock = $defaultVariant ? $defaultVariant->availableStock() : 0;
                        $unitPrice = $defaultVariant ? $defaultVariant->priceForTier($activeTier, 1) : 0.0;
                    @endphp
                    <div
                        wire:key="live-search-item-{{ $product->id }}"
                        class="flex items-center gap-3 rounded-xl border-b border-glamora-blush/20 p-2 transition last:border-none hover:bg-glamora-cream/60"
                    >
                        <a
                            href="{{ route('product.show', $product->slug) }}"
                            class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-glamora-blush/30 bg-glamora-cream"
                        >
                            <img
                                src="{{ $defaultVariant?->image_url ?? $product->image_url }}"
                                alt="{{ $product->name }}"
                                class="h-full w-full object-cover"
                            >
                        </a>

                        <div class="min-w-0 flex-1">
                            <a
                                href="{{ route('product.show', $product->slug) }}"
                                class="block truncate text-xs font-semibold text-glamora-charcoal hover:text-glamora-terracotta"
                            >
                                {{ $product->name }}
                            </a>
                            <p class="mt-0.5 truncate text-[11px] text-glamora-muted">
                                @if ($product->brand)
                                    <span class="font-medium text-glamora-terracotta">{{ $product->brand->name }}</span>
                                @endif
                                @if ($defaultVariant)
                                    · {{ $defaultVariant->shade_name }}
                                    <span class="font-mono text-[10px]">({{ $defaultVariant->sku }})</span>
                                @endif
                            </p>
                        </div>

                        @if ($defaultVariant)
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-xs font-bold text-glamora-charcoal">
                                    S/ {{ number_format($unitPrice, 2) }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="quickAdd({{ $defaultVariant->id }})"
                                    @disabled($variantStock <= 0)
                                    class="rounded-full bg-glamora-charcoal px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-glamora-terracotta disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    {{ $variantStock > 0 ? '+ Bolsa' : 'Agotado' }}
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-4 text-center">
                        <p class="text-xs font-medium text-glamora-muted">
                            Sin resultados para "<span class="font-semibold text-glamora-charcoal">{{ $term }}</span>".
                        </p>
                    </div>
                @endforelse

                @if ($results->isNotEmpty())
                    <div class="mt-2 border-t border-glamora-blush/25 pt-2 text-center">
                        <a
                            href="{{ route('catalog.index', ['buscar' => $term]) }}"
                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-glamora-terracotta hover:underline"
                        >
                            Ver todos los resultados para "{{ $term }}" &rarr;
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
