<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CosmeticImageResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'country_origin',
    'description',
    'logo_path',
    'banner_path',
    'website_url',
    'is_cruelty_free',
    'is_featured',
    'is_active',
    'sort_order',
])]
class Brand extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_cruelty_free' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function getLogoUrlAttribute(): string
    {
        return CosmeticImageResolver::resolve($this->logo_path, 'brand', $this->slug);
    }

    public function getBannerUrlAttribute(): string
    {
        return CosmeticImageResolver::resolve($this->banner_path, 'brand_banner', $this->slug);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->getLogoUrlAttribute();
    }
}
