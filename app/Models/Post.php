<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CosmeticImageResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'author_id',
    'title',
    'slug',
    'category_tag',
    'excerpt',
    'content',
    'cover_image',
    'reading_time_minutes',
    'is_published',
    'published_at',
    'meta_title',
    'meta_description',
])]
class Post extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reading_time_minutes' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(PostProduct::class)
            ->withPivot(['sort_order', 'custom_callout'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }

    public function getCoverUrlAttribute(): string
    {
        return CosmeticImageResolver::resolve($this->cover_image, 'post', $this->slug);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->getCoverUrlAttribute();
    }

    /**
     * Sanitiza HTML editorial del artículo mediante lista blanca DOM estricta,
     * eliminando etiquetas activas/peligrosas y todo atributo HTML.
     */
    public function safeHtmlContent(): string
    {
        $raw = trim((string) $this->content);
        if ($raw === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousErrorState = libxml_use_internal_errors(true);

        $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            . '<body><div id="glamora-prose-root">'
            . $raw
            . '</div></body></html>';

        $dom->loadHTML($wrapped, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorState);

        $root = $dom->getElementById('glamora-prose-root');
        if ($root === null) {
            return e(strip_tags($raw));
        }

        $allowedTags = ['p', 'br', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'blockquote'];
        $dropEntirely = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'noscript', 'template', 'form'];

        $this->sanitizeDomNode($root, $allowedTags, $dropEntirely);

        $output = '';
        foreach ($root->childNodes as $child) {
            $fragment = $dom->saveHTML($child);
            if (is_string($fragment)) {
                $output .= $fragment;
            }
        }

        return $output;
    }

    /**
     * @param  array<int, string>  $allowedTags
     * @param  array<int, string>  $dropEntirely
     */
    private function sanitizeDomNode(\DOMNode $node, array $allowedTags, array $dropEntirely): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child === null) {
                continue;
            }

            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }

            if (! ($child instanceof \DOMElement)) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, $dropEntirely, true)) {
                $node->removeChild($child);
                continue;
            }

            $this->sanitizeDomNode($child, $allowedTags, $dropEntirely);

            while ($child->attributes !== null && $child->attributes->length > 0) {
                $attr = $child->attributes->item(0);
                if ($attr === null) {
                    break;
                }
                $child->removeAttributeNode($attr);
            }

            if (! in_array($tag, $allowedTags, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
            }
        }
    }
}
