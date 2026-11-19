<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CosmeticImageResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'brand_id',
    'name',
    'slug',
    'short_description',
    'description',
    'sanitary_registration',
    'ingredients',
    'how_to_use',
    'skin_type',
    'finish_type',
    'min_stock_alert',
    'reorder_point',
    'is_featured',
    'is_new_launch',
    'is_b2b_kit',
    'is_active',
    'meta_title',
    'meta_description',
    'main_image',
    'gallery',
])]
class Product extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_stock_alert' => 'integer',
            'reorder_point' => 'integer',
            'is_featured' => 'boolean',
            'is_new_launch' => 'boolean',
            'is_b2b_kit' => 'boolean',
            'is_active' => 'boolean',
            'gallery' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)
            ->using(PostProduct::class)
            ->withPivot(['sort_order', 'custom_callout'])
            ->withTimestamps();
    }

    public function totalAvailableStock(): int
    {
        if ($this->relationLoaded('variants')) {
            return (int) $this->variants
                ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
                ->sum(fn (ProductVariant $variant): int => $variant->availableStock());
        }

        return (int) ProductBatch::query()
            ->available()
            ->whereIn('product_variant_id', $this->variants()->active()->select('id'))
            ->reorder()
            ->selectRaw('COALESCE(SUM(current_quantity - reserved_quantity), 0) as aggregate_stock')
            ->value('aggregate_stock');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeNewLaunches(Builder $query): Builder
    {
        return $query->where('is_new_launch', true);
    }

    public function scopeB2bKits(Builder $query): Builder
    {
        return $query->where('is_b2b_kit', true);
    }

    public function getImageUrlAttribute(): string
    {
        return CosmeticImageResolver::resolve($this->main_image, 'product', $this->slug);
    }

    /**
     * @return list<string>
     */
    public function getGalleryUrlsAttribute(): array
    {
        return CosmeticImageResolver::resolveGallery($this->gallery, $this->main_image, $this->slug);
    }
}
