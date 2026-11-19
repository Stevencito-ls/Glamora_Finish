<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    {{-- Top Controls: Search, View Switcher, Tier Preview & Sort --}}
    <div class="mb-8 flex flex-col gap-4 rounded-3xl border border-glamora-blush/35 bg-white p-5 shadow-xs lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-1 flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="relative min-w-[230px] flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-glamora-muted">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <path stroke-linecap="round" d="m21 21-4.35-4.35" />
                    </svg>
                </span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar producto, tono, SKU o registro DIGEMID..."
                    class="input-glamora pl-10"
                >
            </div>

            {{-- Sort --}}
            <div class="w-full sm:w-52">
                <label for="catalog-sort" class="sr-only">Ordenar por</label>
                <select id="catalog-sort" wire:model.live="sort" class="input-glamora">
                    <option value="featured">Destacados Glamora</option>
                    <option value="newest">Novedades y lanzamientos</option>
                    <option value="price_asc">Precio: menor a mayor</option>
                    <option value="price_desc">Precio: mayor a menor</option>
                    <option value="name_asc">Orden alfabético (A–Z)</option>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            {{-- Tier Scale Preview --}}
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-glamora-muted">Escala:</span>
                <select
                    wire:model.live="pricingTier"
                    aria-label="Previsualizar escala de precios"
                    class="rounded-full border border-glamora-charcoal/15 bg-glamora-cream px-3.5 py-1.5 text-xs font-semibold text-glamora-charcoal focus:border-glamora-terracotta focus:outline-none"
                >
                    @foreach (\App\Enums\CustomerTier::cases() as $tierCase)
                        <option wire:key="tier-opt-{{ $tierCase->value }}" value="{{ $tierCase->value }}">{{ $tierCase->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- View Mode Toggle --}}
            <div class="inline-flex rounded-full border border-glamora-charcoal/15 bg-glamora-cream p-1" role="group" aria-label="Cambiar vista del catálogo">
                <button
                    type="button"
                    wire:click="setViewMode('grid')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-semibold transition',
                        'bg-glamora-charcoal text-glamora-cream shadow-xs' => $viewMode === 'grid',
                        'text-glamora-muted hover:text-glamora-charcoal' => $viewMode !== 'grid',
                    ])
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1" />
                        <rect x="14" y="3" width="7" height="7" rx="1" />
                        <rect x="3" y="14" width="7" height="7" rx="1" />
                        <rect x="14" y="14" width="7" height="7" rx="1" />
                    </svg>
                    Cuadrícula Visual
                </button>
                <button
                    type="button"
                    wire:click="setViewMode('matrix')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-semibold transition',
                        'bg-glamora-charcoal text-glamora-cream shadow-xs' => $viewMode === 'matrix',
                        'text-glamora-muted hover:text-glamora-charcoal' => $viewMode !== 'matrix',
                    ])
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M3 6h18M3 12h18M3 18h18" />
                    </svg>
                    Matriz por Tonos
                </button>
            </div>
        </div>
    </div>

    {{-- Feedback Banner --}}
    @if ($feedbackMessage)
        <div
            @class([
                'mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-5 py-3.5 text-sm',
                'border-emerald-200 bg-emerald-50 text-emerald-900' => ! $feedbackIsError,
                'border-rose-200 bg-rose-50 text-rose-900' => $feedbackIsError,
            ])
        >
            <span class="font-medium">{{ $feedbackMessage }}</span>
            @if (! $feedbackIsError)
                <button
                    type="button"
                    wire:click="$dispatch('open-cart-drawer')"
                    class="rounded-full bg-glamora-charcoal px-4 py-1.5 text-xs font-semibold text-glamora-cream transition hover:bg-glamora-terracotta"
                >
                    Abrir Bolsa
                </button>
            @endif
        </div>
    @endif

    @if ($previewTier !== $activeTier)
        <div class="mb-6 rounded-2xl border border-glamora-gold/40 bg-glamora-gold/10 px-5 py-3 text-xs text-glamora-charcoal">
            <strong>Modo Simulación ({{ $previewTier->label() }}):</strong> Estás visualizando precios referenciales para {{ $previewTier->label() }}. Al agregar a la bolsa se aplicará tu tarifa vigente ({{ $activeTier->label() }}).
            <a href="{{ route('wholesale.index') }}" class="ml-1 font-semibold text-glamora-terracotta underline">Solicitar calificación B2B &rarr;</a>
        </div>
    @endif

    <div class="grid gap-8 lg:grid-cols-12">
        {{-- Left Sidebar Filters --}}
        <aside class="space-y-6 lg:col-span-3">
            <div class="card-glamora p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">Filtros</h2>
                    @if ($category || $brand || $search || $skinType || $finishType || $onlyB2bKits)
                        <button
                            type="button"
                            wire:click="clearFilters"
                            class="text-xs font-semibold text-glamora-terracotta hover:underline"
                        >
                            Limpiar todo
                        </button>
                    @endif
                </div>

                {{-- Categorías & Subcategorías --}}
                <div class="border-t border-glamora-blush/25 pt-4">
                    <h3 class="mb-2.5 text-xs font-bold uppercase tracking-wider text-glamora-muted">Categorías</h3>
                    <div class="space-y-1.5">
                        <button
                            type="button"
                            wire:click="$set('category', '')"
                            @class([
                                'flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-xs font-semibold transition',
                                'bg-glamora-charcoal text-glamora-cream' => $category === '',
                                'text-glamora-charcoal hover:bg-glamora-cream' => $category !== '',
                            ])
                        >
                            <span>Todas las categorías</span>
                        </button>

                        @foreach ($categories as $parentCat)
                            <div wire:key="cat-parent-{{ $parentCat->id }}">
                                <button
                                    type="button"
                                    wire:click="$set('category', {{ \Illuminate\Support\Js::from($parentCat->slug) }})"
                                    @class([
                                        'flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-xs font-semibold transition',
                                        'bg-glamora-charcoal text-glamora-cream' => $category === $parentCat->slug,
                                        'text-glamora-charcoal hover:bg-glamora-cream' => $category !== $parentCat->slug,
                                    ])
                                >
                                    <span>{{ $parentCat->name }}</span>
                                </button>

                                @if ($parentCat->children->isNotEmpty())
                                    <div class="mt-1 ml-3 space-y-1 border-l border-glamora-blush/40 pl-2.5">
                                        @foreach ($parentCat->children as $childCat)
                                            <button
                                                wire:key="cat-child-{{ $childCat->id }}"
                                                type="button"
                                                wire:click="$set('category', {{ \Illuminate\Support\Js::from($childCat->slug) }})"
                                                @class([
                                                    'block w-full rounded-lg px-2.5 py-1.5 text-left text-xs transition',
                                                    'bg-glamora-blush/30 font-semibold text-glamora-charcoal' => $category === $childCat->slug,
                                                    'text-glamora-muted hover:bg-glamora-cream hover:text-glamora-charcoal' => $category !== $childCat->slug,
                                                ])
                                            >
                                                {{ $childCat->name }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Marcas --}}
                <div class="mt-5 border-t border-glamora-blush/25 pt-4">
                    <label for="filter-brand" class="mb-2 block text-xs font-bold uppercase tracking-wider text-glamora-muted">
                        Marca
                    </label>
                    <select id="filter-brand" wire:model.live="brand" class="input-glamora text-xs">
                        <option value="">Todas las marcas</option>
                        @foreach ($brands as $b)
                            <option wire:key="brand-opt-{{ $b->id }}" value="{{ $b->slug }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo de Piel --}}
                <div class="mt-4">
                    <label for="filter-skin" class="mb-2 block text-xs font-bold uppercase tracking-wider text-glamora-muted">
                        Tipo de Piel / Cabello
                    </label>
                    <select id="filter-skin" wire:model.live="skinType" class="input-glamora text-xs">
                        <option value="">Todos los perfiles</option>
                        @foreach ($skinTypes as $skin)
                            <option wire:key="skin-opt-{{ md5((string) $skin) }}" value="{{ $skin }}">{{ $skin }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Acabado --}}
                <div class="mt-4">
                    <label for="filter-finish" class="mb-2 block text-xs font-bold uppercase tracking-wider text-glamora-muted">
                        Acabado / Formato
                    </label>
                    <select id="filter-finish" wire:model.live="finishType" class="input-glamora text-xs">
                        <option value="">Todos los acabados</option>
                        @foreach ($finishTypes as $finish)
                            <option wire:key="finish-opt-{{ md5((string) $finish) }}" value="{{ $finish }}">{{ $finish }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Kits B2B Toggle --}}
                <div class="mt-5 border-t border-glamora-blush/25 pt-4">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-glamora-gold/35 bg-glamora-cream/70 p-3">
                        <input
                            type="checkbox"
                            wire:model.live="onlyB2bKits"
                            class="h-4 w-4 rounded border-glamora-charcoal/30 text-glamora-terracotta focus:ring-glamora-gold"
                        >
                        <div>
                            <span class="block text-xs font-bold text-glamora-charcoal">Solo Kits &amp; Packs B2B</span>
                            <span class="block text-[11px] text-glamora-muted">Exhibidores y cajas para salón</span>
                        </div>
                    </label>
                </div>
            </div>
        </aside>

        {{-- Main Content Area --}}
        <section class="lg:col-span-9">
            <div wire:loading.flex wire:target="category,brand,search,skinType,finishType,onlyB2bKits,sort,viewMode,pricingTier,clearFilters,gotoPage,previousPage,nextPage" class="mb-4 items-center gap-2 rounded-xl border border-glamora-blush/40 bg-white px-4 py-2 text-xs text-glamora-muted">
                <span class="h-2 w-2 animate-ping rounded-full bg-glamora-terracotta"></span>
                Actualizando catálogo en tiempo real...
            </div>

            {{-- Skeleton Loader (#F3ECE4) al filtrar, buscar o cambiar vista --}}
            <div
                wire:loading
                wire:target="category,brand,search,skinType,finishType,onlyB2bKits,sort,viewMode,pricingTier,clearFilters,gotoPage,previousPage,nextPage"
                class="w-full"
            >
                <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    @for ($sk = 0; $sk < 6; $sk++)
                        <div class="card-glamora overflow-hidden p-4">
                            <div class="skeleton-glamora aspect-square w-full rounded-xl"></div>
                            <div class="mt-4 space-y-2.5">
                                <div class="skeleton-glamora h-3 w-1/3 rounded-full"></div>
                                <div class="skeleton-glamora h-5 w-4/5 rounded-full"></div>
                                <div class="skeleton-glamora h-3 w-full rounded-full"></div>
                                <div class="flex gap-2 pt-2">
                                    <div class="skeleton-glamora h-6 w-6 rounded-full"></div>
                                    <div class="skeleton-glamora h-6 w-6 rounded-full"></div>
                                    <div class="skeleton-glamora h-6 w-6 rounded-full"></div>
                                </div>
                                <div class="flex items-center justify-between pt-3">
                                    <div class="skeleton-glamora h-6 w-20 rounded-full"></div>
                                    <div class="skeleton-glamora h-9 w-28 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <div wire:loading.remove wire:target="category,brand,search,skinType,finishType,onlyB2bKits,sort,viewMode,pricingTier,clearFilters,gotoPage,previousPage,nextPage">
            @if ($viewMode !== 'matrix')
                {{-- VISTA 1: CUADRÍCULA VISUAL --}}
                @if ($products !== null && $products->count() > 0)
                    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($products as $product)
                            <div wire:key="catalog-grid-{{ $product->id }}-{{ $previewTier->value }}">
                                @include('storefront.partials.product-card', [
                                    'product' => $product,
                                    'pricingTier' => $previewTier,
                                    'useLivewireAdd' => true,
                                    'badges' => $productBadges[$product->id] ?? [],
                                ])
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>
                @else
                    <div class="card-glamora p-12 text-center">
                        <h3 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                            No encontramos fórmulas con esos filtros
                        </h3>
                        <p class="mt-2 text-sm text-glamora-muted">
                            Prueba limpiando la búsqueda o explorando todas las categorías disponibles.
                        </p>
                        <button type="button" wire:click="clearFilters" class="btn-glamora-primary mt-5">
                            Restablecer filtros
                        </button>
                    </div>
                @endif
            @else
                {{-- VISTA 2: MATRIZ DE PEDIDO RÁPIDO POR TONOS --}}
                <div class="card-glamora overflow-hidden">
                    {{-- Bulk Order Action Bar --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-glamora-blush/35 bg-glamora-charcoal px-6 py-4 text-glamora-cream">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-glamora-gold">
                                Matriz de Pedido Rápido por Tonos · Escala {{ $previewTier->label() }}
                            </p>
                            <p class="mt-0.5 text-xs text-glamora-cream/80">
                                Ingresa cantidades por tono o presentación y agrega todo el lote en un clic.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-4">
                            <div class="text-right">
                                <span class="block text-[10px] uppercase tracking-wider text-glamora-cream/65">
                                    Selección ({{ $matrixSelectedLines }} tonos / {{ $matrixSelectedUnits }} uds.)
                                </span>
                                <span class="font-serif text-xl font-bold text-glamora-gold">
                                    S/ {{ number_format($matrixSelectedTotal, 2) }}
                                </span>
                            </div>

                            <button
                                type="button"
                                wire:click="addBatchFromMatrix"
                                @disabled($matrixSelectedUnits === 0)
                                class="btn-glamora-terracotta px-5 py-2.5 text-xs uppercase tracking-wider"
                            >
                                Agregar selección a la bolsa
                            </button>
                        </div>
                    </div>

                    @if ($variants !== null && $variants->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-glamora-blush/30 text-left text-sm">
                                <thead class="bg-glamora-cream/80 text-[11px] font-bold uppercase tracking-wider text-glamora-muted">
                                    <tr>
                                        <th class="px-4 py-3.5">Tono / Producto</th>
                                        <th class="px-4 py-3.5">SKU</th>
                                        <th class="px-4 py-3.5">Contenido</th>
                                        <th class="px-4 py-3.5">Disponible</th>
                                        <th class="px-4 py-3.5">Precio Unit.</th>
                                        <th class="px-4 py-3.5">Cantidad</th>
                                        <th class="px-4 py-3.5 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-glamora-blush/20 bg-white">
                                    @foreach ($variants as $variant)
                                        @php
                                            $stock = $variant->availableStock();
                                            $inputQty = max(1, (int) ($matrixQuantities[$variant->id] ?? 1));
                                            $unitPrice = $variant->priceForTier($previewTier, $inputQty);
                                            $measure = $variant->volume_ml !== null
                                                ? number_format((float) $variant->volume_ml, 1) . ' ml'
                                                : ($variant->weight_grams !== null ? number_format((float) $variant->weight_grams, 0) . ' g' : 'Estándar');
                                            $varImg = $variant->image_url;
                                            $safeHex = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $variant->hex_color) === 1
                                                ? (string) $variant->hex_color
                                                : '#C87D72';
                                        @endphp
                                        <tr wire:key="matrix-row-{{ $variant->id }}" class="transition hover:bg-glamora-cream/40">
                                            <td class="px-4 py-3.5">
                                                <div class="flex items-center gap-3">
                                                    <span
                                                        class="h-7 w-7 shrink-0 rounded-full border border-black/15 shadow-xs"
                                                        style="background-color: {{ $safeHex }}"
                                                        title="{{ $safeHex }}"
                                                    ></span>
                                                    <img
                                                        src="{{ $varImg }}"
                                                        alt="{{ $variant->shade_name }}"
                                                        class="hidden h-10 w-10 shrink-0 rounded-lg border border-glamora-blush/30 object-cover sm:block"
                                                    >
                                                    <div class="min-w-0">
                                                        <a
                                                            href="{{ route('product.show', $variant->product->slug) }}"
                                                            class="block truncate font-semibold text-glamora-charcoal hover:text-glamora-terracotta"
                                                        >
                                                            {{ $variant->product->name }}
                                                        </a>
                                                        <span class="block text-xs text-glamora-muted">
                                                            {{ $variant->shade_name }} · {{ $variant->product->brand?->name }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-4 py-3.5 font-mono text-xs text-glamora-muted">
                                                {{ $variant->sku }}
                                            </td>

                                            <td class="px-4 py-3.5 text-xs text-glamora-charcoal">
                                                {{ $measure }}
                                            </td>

                                            <td class="px-4 py-3.5">
                                                @if ($stock > 10)
                                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                                        {{ $stock }} uds.
                                                    </span>
                                                @elseif ($stock > 0)
                                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                        {{ $stock }} uds. (Bajo)
                                                    </span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                                                        Agotado
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="px-4 py-3.5">
                                                <div class="font-semibold text-glamora-charcoal">
                                                    S/ {{ number_format($unitPrice, 2) }}
                                                </div>
                                                @if ($unitPrice < (float) $variant->base_price)
                                                    <div class="text-[11px] text-glamora-muted line-through">
                                                        S/ {{ number_format((float) $variant->base_price, 2) }}
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="px-4 py-3.5">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ max(0, $stock) }}"
                                                    wire:model.live="matrixQuantities.{{ $variant->id }}"
                                                    placeholder="0"
                                                    @disabled($stock <= 0)
                                                    aria-label="Cantidad para {{ $variant->sku }}"
                                                    class="w-20 rounded-xl border border-glamora-charcoal/20 bg-glamora-cream/60 px-3 py-1.5 text-center text-xs font-semibold text-glamora-charcoal focus:border-glamora-terracotta focus:outline-none disabled:opacity-40"
                                                >
                                            </td>

                                            <td class="px-4 py-3.5 text-right">
                                                <button
                                                    type="button"
                                                    wire:click="addVariantFromMatrix({{ $variant->id }})"
                                                    @disabled($stock <= 0)
                                                    class="rounded-full bg-glamora-charcoal px-3.5 py-1.5 text-xs font-semibold text-glamora-cream transition hover:bg-glamora-terracotta disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    Agregar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-glamora-blush/25 bg-glamora-cream/40 px-6 py-4">
                            {{ $variants->links() }}
                        </div>
                    @else
                        <div class="p-12 text-center">
                            <h3 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                                Sin variantes que coincidan con tu búsqueda
                            </h3>
                            <button type="button" wire:click="clearFilters" class="btn-glamora-primary mt-4">
                                Ver todo el catálogo
                            </button>
                        </div>
                    @endif
                </div>
            @endif
            </div>
        </section>
    </div>
</div>
