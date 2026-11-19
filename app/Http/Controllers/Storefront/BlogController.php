<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\CartSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $activeTag = trim((string) $request->query('etiqueta', ''));

        $tags = Post::query()
            ->published()
            ->whereNotNull('category_tag')
            ->reorder('category_tag')
            ->distinct()
            ->pluck('category_tag')
            ->values();

        $posts = Post::query()
            ->published()
            ->with('author')
            ->when($activeTag !== '', fn ($q) => $q->where('category_tag', $activeTag))
            ->paginate(9)
            ->withQueryString();

        return view('storefront.blog.index', [
            'posts' => $posts,
            'tags' => $tags,
            'activeTag' => $activeTag,
        ]);
    }

    public function show(string $slug, CartSessionService $cartService): View
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'author',
                'products' => fn ($q) => $q->active()->with([
                    'brand',
                    'category',
                    'variants' => fn ($v) => $v->active()->orderByDesc('is_default')->orderBy('id')->with(['batches', 'tieredPrices']),
                ]),
            ])
            ->firstOrFail();

        $relatedPosts = Post::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->with('author')
            ->take(2)
            ->get();

        return view('storefront.blog.show', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'pricingTier' => $cartService->activeTier(),
        ]);
    }
}
