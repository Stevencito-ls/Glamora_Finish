<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartSessionService;
use App\Services\CrossSellService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function show(
        string $slug,
        CartSessionService $cartService,
        CrossSellService $crossSellService,
    ): View {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'category.parent',
                'brand',
                'variants' => fn ($q) => $q->active()
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->with([
                        'tieredPrices',
                        'batches' => fn ($b) => $b->availableForFefo()->with('warehouseLocation'),
                    ]),
            ])
            ->firstOrFail();

        $activeTier = $cartService->activeTier();

        $variantsData = $product->variants->map(function (ProductVariant $variant) use ($product): array {
            $variant->setRelation('product', $product);
            $nextBatch = $variant->batches->first();

            $tierRows = [];
            foreach (CustomerTier::cases() as $tier) {
                $rules = $variant->tieredPrices
                    ->where('customer_tier', $tier)
                    ->sortBy('min_quantity')
                    ->values();

                if ($rules->isEmpty()) {
                    $price = $variant->priceForTier($tier, 1);
                    $discount = (float) $variant->base_price > 0
                        ? round(max(0.0, (1 - ($price / (float) $variant->base_price)) * 100), 1)
                        : 0.0;

                    $tierRows[] = [
                        'tier' => $tier->value,
                        'tier_label' => $tier->label(),
                        'min_quantity' => 1,
                        'max_quantity' => null,
                        'range_label' => '1+ uds.',
                        'unit_price' => $price,
                        'discount_percent' => $discount,
                        'is_wholesale' => $tier !== CustomerTier::Minorista,
                    ];
                } else {
                    foreach ($rules as $rule) {
                        $rangeLabel = $rule->max_quantity !== null
                            ? "{$rule->min_quantity} - {$rule->max_quantity} uds."
                            : "{$rule->min_quantity}+ uds.";

                        $tierRows[] = [
                            'tier' => $tier->value,
                            'tier_label' => $tier->label(),
                            'min_quantity' => $rule->min_quantity,
                            'max_quantity' => $rule->max_quantity,
                            'range_label' => $rangeLabel,
                            'unit_price' => (float) $rule->unit_price,
                            'discount_percent' => (float) $rule->discount_percent,
                            'is_wholesale' => $tier !== CustomerTier::Minorista,
                        ];
                    }
                }
            }

            $measure = null;
            if ($variant->volume_ml !== null) {
                $measure = number_format((float) $variant->volume_ml, 1) . ' ml';
            } elseif ($variant->weight_grams !== null) {
                $measure = number_format((float) $variant->weight_grams, 0) . ' g';
            }

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'shade_name' => $variant->shade_name,
                'hex_color' => preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $variant->hex_color) === 1
                    ? (string) $variant->hex_color
                    : '#C87D72',
                'measure' => $measure ?? 'Formato estándar',
                'barcode' => $variant->barcode,
                'base_price' => (float) $variant->base_price,
                'wholesale_base_price' => (float) $variant->wholesale_base_price,
                'image_url' => $variant->image_url,
                'available_stock' => $variant->availableStock(),
                'next_fefo_batch' => $nextBatch !== null ? [
                    'batch_number' => $nextBatch->batch_number,
                    'expiration_date' => $nextBatch->expiration_date?->format('d/m/Y'),
                    'days_until_expiration' => $nextBatch->daysUntilExpiration(),
                    'available_qty' => $nextBatch->availableQuantity(),
                    'warehouse_code' => $nextBatch->warehouseLocation?->code,
                    'warehouse_zone' => $nextBatch->warehouseLocation?->zone,
                ] : null,
                'tiered_prices' => $tierRows,
                'minorista_volume_price' => $variant->priceForTier(CustomerTier::Minorista, 3),
            ];
        })->values()->all();

        $relatedProducts = Product::query()
            ->active()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product): void {
                $q->where('category_id', $product->category_id)
                    ->orWhere('brand_id', $product->brand_id);
            })
            ->with([
                'brand',
                'category',
                'variants' => fn ($q) => $q->active()->orderByDesc('is_default')->orderBy('id')->with(['batches', 'tieredPrices']),
            ])
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->take(4)
            ->get();

        $routineProducts = $crossSellService->completeRoutineFor($product, 4);
        $bestsellerIds = $crossSellService->bestsellerIds();
        $productBadges = $crossSellService->badgesFor(
            collect([$product])->concat($relatedProducts)->concat($routineProducts),
            $bestsellerIds
        );

        $reviews = $product->approvedReviews()
            ->with('user')
            ->get();

        $reviewsCount = $reviews->count();
        $averageRating = $reviewsCount > 0 ? round((float) $reviews->avg('rating'), 1) : 0.0;
        $ratingDistribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $count = $reviews->where('rating', $star)->count();
            $ratingDistribution[$star] = [
                'count' => $count,
                'percent' => $reviewsCount > 0 ? (int) round(($count / $reviewsCount) * 100) : 0,
            ];
        }

        $user = Auth::user();
        $userReview = null;
        $canSubmitReview = false;
        $userHasVerifiedPurchase = false;

        if ($user instanceof User) {
            $userReview = $product->reviews()->where('user_id', $user->id)->first();
            $canSubmitReview = $userReview === null;
            $userHasVerifiedPurchase = Order::query()
                ->where('user_id', $user->id)
                ->where('status', OrderStatus::Entregado->value)
                ->whereHas('items.variant', fn ($q) => $q->where('product_id', $product->id))
                ->exists();
        }

        $galleryImages = $product->gallery_urls;

        return view('storefront.products.show', [
            'product' => $product,
            'variantsData' => $variantsData,
            'galleryImages' => $galleryImages,
            'relatedProducts' => $relatedProducts,
            'routineProducts' => $routineProducts,
            'bestsellerIds' => $bestsellerIds,
            'productBadges' => $productBadges,
            'reviews' => $reviews,
            'reviewsCount' => $reviewsCount,
            'averageRating' => $averageRating,
            'ratingDistribution' => $ratingDistribution,
            'userReview' => $userReview,
            'canSubmitReview' => $canSubmitReview,
            'userHasVerifiedPurchase' => $userHasVerifiedPurchase,
            'activeTier' => $activeTier,
        ]);
    }
}
