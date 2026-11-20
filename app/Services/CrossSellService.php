<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrossSellService
{
    /**
     * @var list<int>|null
     */
    private ?array $memoizedBestsellerIds = null;

    private int $memoizedBestsellersLimit = 0;

    /**
     * Returns top selling active product IDs using ONLY_FULL_GROUP_BY safe aggregation.
     *
     * @return list<int>
     */
    public function bestsellerIds(int $limit = 6): array
    {
        $limit = max(1, $limit);

        if ($this->memoizedBestsellerIds !== null && $this->memoizedBestsellersLimit >= $limit) {
            return array_slice($this->memoizedBestsellerIds, 0, $limit);
        }

        try {
            $rows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNotIn('orders.status', [
                    OrderStatus::Cancelado->value,
                    OrderStatus::Cotizacion->value,
                ])
                ->where('products.is_active', true)
                ->whereNull('products.deleted_at')
                ->where('product_variants.is_active', true)
                ->groupBy('product_variants.product_id')
                ->selectRaw('product_variants.product_id as product_id, SUM(order_items.quantity) as total_sold')
                ->orderByDesc('total_sold')
                ->orderBy('product_variants.product_id')
                ->limit($limit)
                ->pluck('product_id');

            $ids = $rows->map(static fn (mixed $id): int => (int) $id)->values()->all();
            $this->memoizedBestsellerIds = $ids;
            $this->memoizedBestsellersLimit = $limit;

            return $ids;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Computes promotional and FEFO scarcity badges for a collection of products without N+1 queries.
     *
     * @param  iterable<int, Product>  $products
     * @param  list<int>|null  $bestsellerIds
     * @return array<int, array{
     *     is_bestseller: bool,
     *     is_low_stock: bool,
     *     available_stock: int,
     *     is_new: bool,
     *     is_b2b: bool,
     *     badges: list<array{key: string, label: string, tone: string}>
     * }>
     */
    public function badgesFor(iterable $products, ?array $bestsellerIds = null): array
    {
        $productMap = [];
        $missingStockIds = [];

        foreach ($products as $product) {
            if (! $product instanceof Product) {
                continue;
            }

            $productMap[$product->id] = $product;

            $hasLoadedStock = $product->relationLoaded('variants')
                && ($product->variants->isEmpty() || $product->variants->every(
                    static fn (ProductVariant $variant): bool => $variant->relationLoaded('batches')
                ));

            if (! $hasLoadedStock) {
                $missingStockIds[] = $product->id;
            }
        }

        if ($productMap === []) {
            return [];
        }

        $bestsellers = $bestsellerIds ?? $this->bestsellerIds();
        $batchStockLookup = [];

        if ($missingStockIds !== []) {
            $today = now()->toDateString();
            $batchStockLookup = DB::table('product_variants')
                ->leftJoin('product_batches', function ($join) use ($today): void {
                    $join->on('product_batches.product_variant_id', '=', 'product_variants.id')
                        ->where('product_batches.status', '=', 'active')
                        ->whereDate('product_batches.expiration_date', '>', $today)
                        ->whereColumn('product_batches.current_quantity', '>', 'product_batches.reserved_quantity');
                })
                ->whereIn('product_variants.product_id', array_values(array_unique($missingStockIds)))
                ->where('product_variants.is_active', true)
                ->groupBy('product_variants.product_id')
                ->selectRaw('product_variants.product_id as product_id, COALESCE(SUM(CASE WHEN product_batches.current_quantity > product_batches.reserved_quantity THEN product_batches.current_quantity - product_batches.reserved_quantity ELSE 0 END), 0) as available_stock')
                ->pluck('available_stock', 'product_id')
                ->all();
        }

        $result = [];

        foreach ($productMap as $productId => $product) {
            $availableStock = array_key_exists($productId, $batchStockLookup)
                ? (int) $batchStockLookup[$productId]
                : $product->totalAvailableStock();

            $isBestseller = in_array($productId, $bestsellers, true);
            $alertThreshold = max(1, (int) $product->min_stock_alert);
            $isLowStock = $availableStock > 0 && $availableStock <= $alertThreshold;
            $isNew = (bool) $product->is_new_launch;
            $isB2b = (bool) $product->is_b2b_kit;

            $badges = [];
            if ($isB2b) {
                $badges[] = ['key' => 'b2b', 'label' => 'Kit B2B', 'tone' => 'charcoal'];
            }
            if ($isBestseller) {
                $badges[] = ['key' => 'bestseller', 'label' => 'Top Ventas', 'tone' => 'gold'];
            }
            if ($isNew) {
                $badges[] = ['key' => 'new', 'label' => 'Nuevo', 'tone' => 'terracotta'];
            }
            if ($isLowStock) {
                $badges[] = ['key' => 'low_stock', 'label' => "Últimas {$availableStock} uds.", 'tone' => 'amber'];
            }

            $result[$productId] = [
                'is_bestseller' => $isBestseller,
                'is_low_stock' => $isLowStock,
                'available_stock' => $availableStock,
                'is_new' => $isNew,
                'is_b2b' => $isB2b,
                'badges' => $badges,
            ];
        }

        return $result;
    }

    /**
     * Suggests complementary products to complete a beauty/skincare routine without N+1 queries.
     *
     * @return EloquentCollection<int, Product>
     */
    public function completeRoutineFor(Product $product, int $limit = 4): EloquentCollection
    {
        $limit = max(1, $limit);
        $product->loadMissing('category');

        $coPurchasedIds = $this->coPurchasedProductIds([$product->id], $limit * 2);

        $siblingCategoryIds = [];
        $parentCategoryId = $product->category?->parent_id;
        if ($parentCategoryId !== null) {
            $siblingCategoryIds = Category::query()
                ->active()
                ->where('parent_id', $parentCategoryId)
                ->where('id', '!=', $product->category_id)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        }

        $siblingIds = $siblingCategoryIds !== []
            ? Product::query()
                ->active()
                ->where('id', '!=', $product->id)
                ->whereIn('category_id', $siblingCategoryIds)
                ->orderByDesc('is_featured')
                ->limit($limit * 2)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all()
            : [];

        $bestsellerIds = array_values(array_diff($this->bestsellerIds($limit * 3), [$product->id]));

        $featuredIds = Product::query()
            ->active()
            ->where('id', '!=', $product->id)
            ->orderByDesc('is_featured')
            ->orderByDesc('is_new_launch')
            ->limit($limit * 3)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $orderedIds = array_values(array_unique(array_merge(
            $coPurchasedIds,
            $siblingIds,
            $bestsellerIds,
            $featuredIds,
        )));

        if ($orderedIds === []) {
            return new EloquentCollection();
        }

        $loaded = $this->loadStorefrontProductsByIds($orderedIds);
        $ordered = new EloquentCollection();

        foreach ($orderedIds as $candidateId) {
            $candidate = $loaded->get($candidateId);
            if ($candidate instanceof Product && $candidate->variants->isNotEmpty()) {
                $ordered->push($candidate);
            }

            if ($ordered->count() >= $limit) {
                break;
            }
        }

        return $ordered;
    }

    /**
     * Suggests in-stock complementary products for the shopping bag drawer / cart page.
     *
     * @param  array<int|string, array<string, mixed>>  $cartItems
     * @return EloquentCollection<int, Product>
     */
    public function recommendationsForCart(array $cartItems, int $limit = 3): EloquentCollection
    {
        $limit = max(1, $limit);
        $cartProductIds = [];
        $missingVariantIds = [];

        foreach ($cartItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! empty($item['product_id'])) {
                $cartProductIds[] = (int) $item['product_id'];
            } elseif (! empty($item['product_variant_id'])) {
                $missingVariantIds[] = (int) $item['product_variant_id'];
            }
        }

        if ($missingVariantIds !== []) {
            $resolvedProductIds = ProductVariant::query()
                ->whereIn('id', array_values(array_unique($missingVariantIds)))
                ->pluck('product_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $cartProductIds = array_merge($cartProductIds, $resolvedProductIds);
        }

        $cartProductIds = array_values(array_unique(array_filter($cartProductIds)));

        $coPurchasedIds = $cartProductIds !== []
            ? $this->coPurchasedProductIds($cartProductIds, $limit * 3)
            : [];

        $bestsellerIds = array_values(array_diff($this->bestsellerIds($limit * 4), $cartProductIds));

        $fallbackIds = Product::query()
            ->active()
            ->when($cartProductIds !== [], fn ($q) => $q->whereNotIn('id', $cartProductIds))
            ->where('is_b2b_kit', false)
            ->orderByDesc('is_featured')
            ->orderByDesc('is_new_launch')
            ->limit($limit * 4)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $orderedIds = array_values(array_unique(array_merge(
            $coPurchasedIds,
            $bestsellerIds,
            $fallbackIds,
        )));

        if ($orderedIds === []) {
            return new EloquentCollection();
        }

        $loaded = $this->loadStorefrontProductsByIds($orderedIds);
        $recommendations = new EloquentCollection();

        foreach ($orderedIds as $candidateId) {
            $candidate = $loaded->get($candidateId);
            if (! $candidate instanceof Product || $candidate->variants->isEmpty()) {
                continue;
            }

            $hasAvailableVariant = $candidate->variants->contains(
                static fn (ProductVariant $variant): bool => $variant->availableStock() > 0
            );

            if (! $hasAvailableVariant) {
                continue;
            }

            $recommendations->push($candidate);

            if ($recommendations->count() >= $limit) {
                break;
            }
        }

        return $recommendations;
    }

    /**
     * @param  list<int>  $sourceProductIds
     * @return list<int>
     */
    private function coPurchasedProductIds(array $sourceProductIds, int $limit): array
    {
        if ($sourceProductIds === []) {
            return [];
        }

        try {
            $matchingOrderIds = DB::table('order_items')
                ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                ->whereIn('product_variants.product_id', $sourceProductIds)
                ->select('order_items.order_id')
                ->distinct();

            $rows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereIn('order_items.order_id', $matchingOrderIds)
                ->whereNotIn('product_variants.product_id', $sourceProductIds)
                ->where('orders.status', '!=', OrderStatus::Cancelado->value)
                ->where('products.is_active', true)
                ->whereNull('products.deleted_at')
                ->where('product_variants.is_active', true)
                ->groupBy('product_variants.product_id')
                ->selectRaw('product_variants.product_id as product_id, SUM(order_items.quantity) as co_qty')
                ->orderByDesc('co_qty')
                ->orderBy('product_variants.product_id')
                ->limit(max(1, $limit))
                ->pluck('product_id');

            return $rows->map(static fn (mixed $id): int => (int) $id)->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return EloquentCollection<int, Product>
     */
    private function loadStorefrontProductsByIds(array $ids): EloquentCollection
    {
        return Product::query()
            ->active()
            ->whereIn('id', $ids)
            ->with([
                'brand',
                'category',
                'variants' => fn ($q) => $q->active()
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->with(['batches', 'tieredPrices']),
            ])
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->get()
            ->keyBy('id');
    }
}
