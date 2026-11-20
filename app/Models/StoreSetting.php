<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SettingService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'value',
    'type',
    'group',
    'label',
    'description',
    'is_public',
])]
class StoreSetting extends Model
{
    protected static function booted(): void
    {
        $clear = static function (): void {
            try {
                app(SettingService::class)->clearCache();
            } catch (\Throwable) {
                // Ignore during early bootstrap/CLI without cache driver
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
            'is_public' => 'boolean',
        ];
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function typedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => in_array(strtolower(trim((string) $this->value)), ['1', 'true', 'yes', 'on'], true),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json' => json_decode((string) $this->value, true),
            default => (string) $this->value,
        };
    }
}
