<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'zone',
    'aisle',
    'rack',
    'shelf',
    'description',
    'temperature_controlled',
    'is_active',
])]
class WarehouseLocation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature_controlled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTemperatureControlled(Builder $query): Builder
    {
        return $query->where('temperature_controlled', true);
    }
}
