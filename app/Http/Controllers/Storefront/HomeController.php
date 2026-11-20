<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\BannerPosition;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Services\CartSessionService;
use App\Services\CrossSellService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(CartSessionService $cartService, CrossSellService $crossSellService): View
    {
        $heroBanners = Banner::query()
            ->active()
            ->forPosition(BannerPosition::HeroPrincipal)
            ->ordered()
            ->get();

        $promoBanners = Banner::query()
            ->active()
            ->forPosition(BannerPosition::PromoSecundario)
            ->ordered()
            ->take(2)
            ->get();

        $categories = Category::query()
            ->active()
            ->root()
            ->ordered()
            ->with(['children' => fn ($q) => $q->active()->ordered()])
            ->get();

        $featuredBrands = Brand::query()
            ->active()
            ->featured()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->get();

        $newLaunches = Product::query()
            ->active()
            ->newLaunches()
            ->with([
                'brand',
                'category',
                'variants' => fn ($q) => $q->active()->orderByDesc('is_default')->orderBy('id')->with(['batches', 'tieredPrices']),
            ])
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->latest('id')
            ->take(4)
            ->get();

        $b2bKits = Product::query()
            ->active()
            ->b2bKits()
            ->with([
                'brand',
                'category',
                'variants' => fn ($q) => $q->active()->orderByDesc('is_default')->orderBy('id')->with(['batches', 'tieredPrices']),
            ])
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->orderBy('id')
            ->take(4)
            ->get();

        $recentPosts = Post::query()
            ->published()
            ->with('author')
            ->take(3)
            ->get();

        $bestsellerIds = $crossSellService->bestsellerIds();
        $productBadges = $crossSellService->badgesFor(
            $newLaunches->concat($b2bKits),
            $bestsellerIds
        );

        return view('storefront.home', [
            'heroBanners' => $heroBanners,
            'promoBanners' => $promoBanners,
            'categories' => $categories,
            'featuredBrands' => $featuredBrands,
            'newLaunches' => $newLaunches,
            'b2bKits' => $b2bKits,
            'recentPosts' => $recentPosts,
            'pricingTier' => $cartService->activeTier(),
            'bestsellerIds' => $bestsellerIds,
            'productBadges' => $productBadges,
        ]);
    }
}
