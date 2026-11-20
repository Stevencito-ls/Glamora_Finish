<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Enums\CustomerTier;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartSessionService;
use App\Services\CrossSellService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogBrowser extends Component
{
    use WithPagination;

    #[Url(as: 'categoria', except: '')]
    public string $category = '';

    #[Url(as: 'marca', except: '')]
    public string $brand = '';

    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    #[Url(as: 'piel', except: '')]
    public string $skinType = '';

    #[Url(as: 'acabado', except: '')]
    public string $finishType = '';

    #[Url(as: 'b2b', except: false)]
    public bool $onlyB2bKits = false;

    #[Url(as: 'orden', except: 'featured')]
    public string $sort = 'featured';

    #[Url(as: 'vista', except: 'grid')]
    public string $viewMode = 'grid';

    public string $pricingTier = 'minorista';

    /**
     * @var array<int|string, int|string>
     */
    public array $matrixQuantities = [];

    public ?string $feedbackMessage = null;

    public bool $feedbackIsError = false;

    public function mount(
        string $initialCategory = '',
        string $initialBrand = '',
        string $initialSearch = '',
        bool $initialOnlyB2b = false,
        string $initialViewMode = 'grid',
    ): void {
        if ($initialCategory !== '' && $this->category === '') {
            $this->category = $initialCategory;
        }
        if ($initialBrand !== '' && $this->brand === '') {
            $this->brand = $initialBrand;
        }
        if ($initialSearch !== '' && $this->search === '') {
            $this->search = $initialSearch;
        }
        if ($initialOnlyB2b && ! $this->onlyB2bKits) {
            $this->onlyB2bKits = true;
        }
        if (in_array($initialViewMode, ['grid', 'matrix'], true)) {
            $this->viewMode = $initialViewMode;
        }
        if (! in_array($this->viewMode, ['grid', 'matrix'], true)) {
            $this->viewMode = 'grid';
        }

        $this->pricingTier = app(CartSessionService::class)->activeTier()->value;
    }

    public function updatedMatrixQuantities(mixed $value, ?string $key = null): void
    {
        if ($key === null) {
            if (! is_array($value)) {
                $this->matrixQuantities = [];

                return;
            }

            $sanitized = [];
            foreach ($value as $varId => $rawQty) {
                $qty = (int) $rawQty;
                if ($qty > 0) {
                    $sanitized[(int) $varId] = min(10000, $qty);
                }
            }

            $this->matrixQuantities = $sanitized;

            return;
        }

        if ($value === '' || $value === null) {
            unset($this->matrixQuantities[$key]);

            return;
        }

        $qty = (int) $value;
        if ($qty <= 0) {
            unset($this->matrixQuantities[$key]);

            return;
        }

        $this->matrixQuantities[$key] = min(10000, $qty);
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedBrand(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSkinType(): void
    {
        $this->resetPage();
    }

    public function updatedFinishType(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyB2bKits(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['grid', 'matrix'], true)) {
            return;
        }

        $this->viewMode = $mode;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'category',
            'brand',
            'search',
            'skinType',
            'finishType',
            'onlyB2bKits',
            'sort',
        ]);
        $this->sort = 'featured';
        $this->resetPage();
    }

    public function quickAdd(int $variantId): void
    {
        $variant = ProductVariant::query()
            ->with(['product.brand', 'tieredPrices', 'batches'])
            ->find($variantId);

        if (! $variant instanceof ProductVariant) {
            $this->feedbackIsError = true;
            $this->feedbackMessage = 'No se encontró la variante seleccionada.';
            $this->dispatch('swal:toast', title: $this->feedbackMessage, icon: 'error');

            return;
        }

        $result = app(CartSessionService::class)->addVariant($variant, 1);

        $this->feedbackIsError = ! $result['added'];
        $this->feedbackMessage = $result['message'];

        $this->dispatch(
            'swal:toast',
            title: $result['message'],
            icon: $result['added'] ? 'success' : 'warning',
            productName: "{$variant->product->name} — {$variant->shade_name}"
        );

        if ($result['added']) {
            $this->dispatch('cart-updated', openDrawer: false);
        }
    }

    public function addVariantFromMatrix(int $variantId): void
    {
        $qty = max(1, (int) ($this->matrixQuantities[$variantId] ?? 1));

        $variant = ProductVariant::query()
            ->with(['product.brand', 'tieredPrices', 'batches'])
            ->find($variantId);

        if (! $variant instanceof ProductVariant) {
            $this->feedbackIsError = true;
            $this->feedbackMessage = 'Variante no encontrada.';
            $this->dispatch('swal:toast', title: $this->feedbackMessage, icon: 'error');

            return;
        }

        $result = app(CartSessionService::class)->addVariant($variant, $qty);
        $this->feedbackIsError = ! $result['added'];
        $this->feedbackMessage = $result['message'];

        $this->dispatch(
            'swal:toast',
            title: $result['message'],
            icon: $result['added'] ? 'success' : 'warning',
            productName: "{$variant->product->name} — {$variant->shade_name}"
        );

        if ($result['added']) {
            unset($this->matrixQuantities[$variantId]);
            $this->dispatch('cart-updated', openDrawer: false);
        }
    }

    public function addBatchFromMatrix(): void
    {
        $result = app(CartSessionService::class)->addBatch($this->matrixQuantities);

        if ($result['lines_added'] === 0) {
            $this->feedbackIsError = true;
            $this->feedbackMessage = $result['warnings'][0] ?? 'Ingresa una cantidad mayor a 0 en al menos un tono.';
            $this->dispatch('swal:toast', title: $this->feedbackMessage, icon: 'warning');

            return;
        }

        foreach ($this->matrixQuantities as $variantId => $qty) {
            if ((int) $qty > 0) {
                unset($this->matrixQuantities[$variantId]);
            }
        }

        $this->feedbackIsError = false;
        $warningSuffix = $result['warnings'] !== [] ? ' (' . implode(' ', $result['warnings']) . ')' : '';
        $this->feedbackMessage = "Selección agregada a la Bolsa: {$result['lines_added']} tono(s), {$result['total_units']} unidades en total.{$warningSuffix}";

        $this->dispatch('swal:toast', title: $this->feedbackMessage, icon: 'success');
        $this->dispatch('cart-updated', openDrawer: true);
    }

    public function render(): View
    {
        $categories = Category::query()
            ->active()
            ->root()
            ->ordered()
            ->with(['children' => fn ($q) => $q->active()->ordered()])
            ->get();

        $brands = Brand::query()
            ->active()
            ->orderBy('sort_order')
            ->get();

        $skinTypes = Product::query()
            ->active()
            ->whereNotNull('skin_type')
            ->distinct()
            ->orderBy('skin_type')
            ->pluck('skin_type');

        $finishTypes = Product::query()
            ->active()
            ->whereNotNull('finish_type')
            ->distinct()
            ->orderBy('finish_type')
            ->pluck('finish_type');

        if (! in_array($this->viewMode, ['grid', 'matrix'], true)) {
            $this->viewMode = 'grid';
        }

        $selectedCategoryIds = $this->resolveSelectedCategoryIds();
        $previewTier = CustomerTier::tryFrom($this->pricingTier) ?? CustomerTier::Minorista;
        $activeTier = app(CartSessionService::class)->activeTier();

        $products = null;
        $variants = null;

        if ($this->viewMode === 'matrix') {
            $variantQuery = ProductVariant::query()
                ->active()
                ->with([
                    'product.brand',
                    'product.category',
                    'tieredPrices',
                    'batches',
                ])
                ->whereHas('product', function (Builder $productQuery) use ($selectedCategoryIds): void {
                    $this->applyProductFilters($productQuery, $selectedCategoryIds, applySearchOnProduct: false);
                });

            if (trim($this->search) !== '') {
                $term = '%' . trim($this->search) . '%';
                $variantQuery->where(function (Builder $q) use ($term): void {
                    $q->where('sku', 'like', $term)
                        ->orWhere('shade_name', 'like', $term)
                        ->orWhereHas('product', function (Builder $pq) use ($term): void {
                            $pq->where('name', 'like', $term)
                                ->orWhere('short_description', 'like', $term)
                                ->orWhere('sanitary_registration', 'like', $term)
                                ->orWhereHas('brand', fn (Builder $bq) => $bq->where('name', 'like', $term));
                        });
                });
            }

            match ($this->sort) {
                'price_asc' => $variantQuery->orderBy('base_price', 'asc'),
                'price_desc' => $variantQuery->orderBy('base_price', 'desc'),
                'name_asc' => $variantQuery->orderBy('shade_name', 'asc'),
                'newest' => $variantQuery->orderByDesc('id'),
                default => $variantQuery->orderBy('product_id')->orderByDesc('is_default')->orderBy('id'),
            };

            $variants = $variantQuery->paginate(16)->withQueryString();
        } else {
            $productQuery = Product::query()
                ->active()
                ->with([
                    'category.parent',
                    'brand',
                    'variants' => fn ($q) => $q->active()->orderByDesc('is_default')->orderBy('id')->with(['tieredPrices', 'batches']),
                ])
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->withMin(['variants as min_base_price' => fn ($q) => $q->active()], 'base_price');

            $this->applyProductFilters($productQuery, $selectedCategoryIds, applySearchOnProduct: true);

            match ($this->sort) {
                'price_asc' => $productQuery->orderBy('min_base_price', 'asc'),
                'price_desc' => $productQuery->orderBy('min_base_price', 'desc'),
                'name_asc' => $productQuery->orderBy('name', 'asc'),
                'newest' => $productQuery->orderByDesc('is_new_launch')->orderByDesc('id'),
                default => $productQuery->orderByDesc('is_featured')->orderBy('id'),
            };

            $products = $productQuery->paginate(9)->withQueryString();
        }

        // Calcular resumen de selección actual en matriz
        $matrixSelectedUnits = 0;
        $matrixSelectedLines = 0;
        $matrixSelectedTotal = 0.0;

        if ($this->viewMode === 'matrix' && $variants !== null) {
            /** @var array<int, ProductVariant> $loadedById */
            $loadedById = $variants->getCollection()->getDictionary();
            $positiveIds = [];
            foreach ($this->matrixQuantities as $varId => $rawQty) {
                if ((int) $rawQty > 0) {
                    $positiveIds[] = (int) $varId;
                }
            }

            $missingIds = array_values(array_diff($positiveIds, array_map('intval', array_keys($loadedById))));
            if ($missingIds !== []) {
                $missingDict = ProductVariant::query()
                    ->with('tieredPrices')
                    ->whereIn('id', $missingIds)
                    ->get()
                    ->getDictionary();
                $loadedById += $missingDict;
            }

            foreach ($this->matrixQuantities as $varId => $rawQty) {
                $qty = min(10000, max(0, (int) $rawQty));
                if ($qty <= 0) {
                    continue;
                }
                $matrixSelectedLines++;
                $matrixSelectedUnits += $qty;
                $variantModel = $loadedById[(int) $varId] ?? null;
                if ($variantModel instanceof ProductVariant) {
                    $matrixSelectedTotal += $variantModel->priceForTier($previewTier, $qty) * $qty;
                }
            }
        }

        $crossSell = app(CrossSellService::class);
        $bestsellerIds = $crossSell->bestsellerIds();
        $productBadges = $products !== null
            ? $crossSell->badgesFor($products->getCollection(), $bestsellerIds)
            : [];

        return view('livewire.storefront.catalog-browser', [
            'categories' => $categories,
            'brands' => $brands,
            'skinTypes' => $skinTypes,
            'finishTypes' => $finishTypes,
            'products' => $products,
            'variants' => $variants,
            'previewTier' => $previewTier,
            'activeTier' => $activeTier,
            'matrixSelectedUnits' => $matrixSelectedUnits,
            'matrixSelectedLines' => $matrixSelectedLines,
            'matrixSelectedTotal' => round($matrixSelectedTotal, 2),
            'bestsellerIds' => $bestsellerIds,
            'productBadges' => $productBadges,
        ]);
    }

    /**
     * @return list<int>
     */
    private function resolveSelectedCategoryIds(): array
    {
        if ($this->category === '') {
            return [];
        }

        $category = Category::query()
            ->active()
            ->with(['children' => fn ($q) => $q->active()])
            ->where('slug', $this->category)
            ->first();

        if ($category === null) {
            return [];
        }

        $ids = [$category->id];
        foreach ($category->children as $child) {
            $ids[] = $child->id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $selectedCategoryIds
     */
    private function applyProductFilters(Builder $query, array $selectedCategoryIds, bool $applySearchOnProduct): void
    {
        $query->where('is_active', true);

        if ($this->category !== '') {
            if ($selectedCategoryIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('category_id', $selectedCategoryIds);
            }
        }

        if ($this->brand !== '') {
            $query->whereHas('brand', fn (Builder $q) => $q->where('slug', $this->brand));
        }

        if ($this->skinType !== '') {
            $query->where('skin_type', $this->skinType);
        }

        if ($this->finishType !== '') {
            $query->where('finish_type', $this->finishType);
        }

        if ($this->onlyB2bKits) {
            $query->where('is_b2b_kit', true);
        }

        if ($applySearchOnProduct && trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('short_description', 'like', $term)
                    ->orWhere('sanitary_registration', 'like', $term)
                    ->orWhereHas('brand', fn (Builder $bq) => $bq->where('name', 'like', $term))
                    ->orWhereHas('variants', function (Builder $vq) use ($term): void {
                        $vq->where('is_active', true)
                            ->where(function (Builder $sq) use ($term): void {
                                $sq->where('sku', 'like', $term)
                                    ->orWhere('shade_name', 'like', $term);
                            });
                    });
            });
        }
    }
}
