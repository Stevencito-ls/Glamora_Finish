<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request, ?string $categoria = null): View
    {
        $activeCategory = null;
        $categorySlug = $categoria ?? (string) $request->query('categoria', '');

        if ($categorySlug !== '') {
            $activeCategory = Category::query()
                ->active()
                ->with('parent')
                ->where('slug', $categorySlug)
                ->first();

            if ($categoria !== null && $activeCategory === null) {
                abort(404);
            }
        }

        return view('storefront.catalog.index', [
            'activeCategory' => $activeCategory,
            'initialCategory' => $activeCategory?->slug ?? '',
            'initialBrand' => (string) $request->query('marca', ''),
            'initialSearch' => (string) $request->query('buscar', ''),
            'initialOnlyB2b' => $request->boolean('b2b'),
            'initialViewMode' => (string) $request->query('vista', 'grid'),
        ]);
    }
}
