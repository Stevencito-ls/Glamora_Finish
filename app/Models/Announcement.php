<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SettingService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'badge_text',
    'link_label',
    'link_url',
    'icon',
    'sort_order',
    'is_active',
    'starts_at',
    'ends_at',
])]
class Announcement extends Model
{
    protected static function booted(): void
    {
        $clear = static function (): void {
            try {
                app(SettingService::class)->clearAnnouncementsCache();
            } catch (\Throwable) {
                // Ignore during early bootstrap/CLI
            }
        };

        static::saved($clear);
        static::deleted($clear);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function safeLinkUrl(): ?string
    {
        return self::sanitizeLinkUrl($this->link_url);
    }

    public static function sanitizeLinkUrl(?string $url): ?string
    {
        $clean = trim((string) $url);

        if (
            $clean === ''
            || preg_match('/[\x00-\x1F\x7F]/', $clean) === 1
            || str_contains($clean, '..')
            || str_contains($clean, '\\')
            || str_starts_with($clean, '//')
        ) {
            return null;
        }

        if (str_starts_with($clean, '/') || str_starts_with($clean, '#')) {
            return $clean;
        }

        if (preg_match('/^https?:\/\//i', $clean) === 1 && filter_var($clean, FILTER_VALIDATE_URL) !== false) {
            $scheme = strtolower((string) parse_url($clean, PHP_URL_SCHEME));
            if (in_array($scheme, ['http', 'https'], true)) {
                return $clean;
            }
        }

        return null;
    }
}
