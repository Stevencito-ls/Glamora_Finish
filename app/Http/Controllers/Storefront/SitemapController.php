<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\Response;
use XMLWriter;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $writeUrl = static function (string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.8') use ($xml): void {
            $xml->startElement('url');
            $xml->writeElement('loc', $loc);

            if ($lastmod !== null && $lastmod !== '') {
                $xml->writeElement('lastmod', $lastmod);
            }

            $xml->writeElement('changefreq', $changefreq);
            $xml->writeElement('priority', $priority);
            $xml->endElement();
        };

        $staticLastMod = now()->startOfDay()->toAtomString();

        $writeUrl(route('home'), $staticLastMod, 'daily', '1.0');
        $writeUrl(route('catalog.index'), $staticLastMod, 'daily', '0.9');
        $writeUrl(route('brands.index'), $staticLastMod, 'weekly', '0.8');
        $writeUrl(route('wholesale.index'), $staticLastMod, 'weekly', '0.8');
        $writeUrl(route('blog.index'), $staticLastMod, 'daily', '0.8');
        $writeUrl(route('tracking.index'), $staticLastMod, 'monthly', '0.5');

        Category::query()
            ->active()
            ->ordered()
            ->get(['id', 'slug', 'updated_at'])
            ->each(function (Category $category) use ($writeUrl): void {
                $writeUrl(
                    route('catalog.category', $category->slug),
                    $category->updated_at?->toAtomString(),
                    'weekly',
                    '0.8',
                );
            });

        Product::query()
            ->active()
            ->orderBy('id')
            ->get(['id', 'slug', 'updated_at'])
            ->each(function (Product $product) use ($writeUrl): void {
                $writeUrl(
                    route('product.show', $product->slug),
                    $product->updated_at?->toAtomString(),
                    'daily',
                    '0.9',
                );
            });

        Post::query()
            ->published()
            ->get(['id', 'slug', 'published_at', 'updated_at'])
            ->each(function (Post $post) use ($writeUrl): void {
                $lastmod = ($post->updated_at ?? $post->published_at)?->toAtomString();

                $writeUrl(
                    route('blog.show', $post->slug),
                    $lastmod,
                    'weekly',
                    '0.7',
                );
            });

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }
}
