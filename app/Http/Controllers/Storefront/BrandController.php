<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Contracts\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        $brands = Brand::query()
            ->active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->with([
                'products' => fn ($q) => $q->active()
                    ->with(['category', 'variants' => fn ($v) => $v->active()->orderByDesc('is_default')->with('batches')])
                    ->orderByDesc('is_featured')
                    ->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->get();

        return view('storefront.brands.index', [
            'brands' => $brands,
        ]);
    }
}
