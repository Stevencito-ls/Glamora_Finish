<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartSessionService;
use App\Services\CrossSellService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CatalogPdfController extends Controller
{
    public function download(
        Request $request,
        CartSessionService $cartService,
        SettingService $settingService,
        CrossSellService $crossSellService,
    ): Response {
        return $this->buildCatalogPdfResponse(
            request: $request,
            cartService: $cartService,
            settingService: $settingService,
            crossSellService: $crossSellService,
            enforcePublicToggle: true,
        );
    }

    public function buildCatalogPdfResponse(
        Request $request,
        CartSessionService $cartService,
        SettingService $settingService,
        CrossSellService $crossSellService,
        bool $enforcePublicToggle = true,
    ): Response {
        if ($enforcePublicToggle && ! $settingService->bool('catalog_pdf_enabled', true)) {
            abort(404, 'La descarga del catálogo PDF se encuentra deshabilitada temporalmente.');
        }

        $categorySlug = trim((string) $request->query('categoria', ''));
        $brandSlug = trim((string) $request->query('marca', ''));
        $onlyB2b = $request->boolean('b2b');

        $selectedCategoryIds = [];
        $activeCategory = null;

        if ($categorySlug !== '') {
            $activeCategory = Category::query()
                ->active()
                ->with(['children' => fn ($q) => $q->active()])
                ->where('slug', $categorySlug)
                ->first();

            if ($activeCategory !== null) {
                $selectedCategoryIds = [$activeCategory->id];
                foreach ($activeCategory->children as $child) {
                    $selectedCategoryIds[] = $child->id;
                }
            }
        }

        $products = Product::query()
            ->active()
            ->with([
                'category.parent',
                'brand',
                'variants' => fn ($q) => $q->active()
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->with(['tieredPrices', 'batches']),
            ])
            ->when($selectedCategoryIds !== [], fn (Builder $q) => $q->whereIn('category_id', $selectedCategoryIds))
            ->when($brandSlug !== '', fn (Builder $q) => $q->whereHas('brand', fn (Builder $bq) => $bq->where('slug', $brandSlug)))
            ->when($onlyB2b, fn (Builder $q) => $q->where('is_b2b_kit', true))
            ->orderByDesc('is_featured')
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $activeTier = $cartService->activeTier();
        $bestsellerIds = $crossSellService->bestsellerIds(10);
        $settings = $settingService->all();
        $theme = $settingService->activeTheme();

        $storeName = (string) ($settings['store_name'] ?? 'Glamora Perú');
        $safeStoreSlug = Str::slug($storeName) ?: 'glamora';

        $pdf = Pdf::loadView('pdf.catalog', [
            'products' => $products,
            'activeTier' => $activeTier,
            'bestsellerIds' => $bestsellerIds,
            'settings' => $settings,
            'theme' => $theme,
            'whatsappNumber' => $settingService->whatsappNumber(),
            'freeShippingThreshold' => $settingService->freeShippingThreshold(),
            'taxPercent' => round($settingService->taxRate() * 100, 1),
            'activeCategory' => $activeCategory,
            'onlyB2b' => $onlyB2b,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Catalogo-{$safeStoreSlug}-" . now()->format('Ymd') . '.pdf');
    }
}
