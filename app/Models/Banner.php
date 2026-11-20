<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BannerPosition;
use App\Support\CosmeticImageResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'subtitle',
    'eyebrow',
    'badge_text',
    'button_text',
    'button_url',
    'secondary_button_text',
    'secondary_button_url',
    'image_path',
    'accent_image_path',
    'position',
    'is_active',
    'sort_order',
])]
class Banner extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => BannerPosition::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPosition(Builder $query, BannerPosition|string $position): Builder
    {
        $value = $position instanceof BannerPosition ? $position->value : $position;

        return $query->where('position', $value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): string
    {
        return CosmeticImageResolver::resolve($this->image_path, 'banner', $this->title);
    }

    public function getAccentImageUrlAttribute(): string
    {
        return CosmeticImageResolver::resolve(
            $this->accent_image_path ?: $this->image_path,
            'product',
            $this->title
        );
    }

    public function safeButtonUrl(): ?string
    {
        return Announcement::sanitizeLinkUrl($this->button_url);
    }

    public function safeSecondaryButtonUrl(): ?string
    {
        return Announcement::sanitizeLinkUrl($this->secondary_button_url);
    }
}
