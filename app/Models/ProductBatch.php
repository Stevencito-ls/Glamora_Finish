<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_variant_id',
    'warehouse_location_id',
    'batch_number',
    'manufacturing_date',
    'expiration_date',
    'initial_quantity',
    'current_quantity',
    'reserved_quantity',
    'unit_cost',
    'supplier_name',
    'status',
    'notes',
])]
class ProductBatch extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiration_date' => 'date',
            'initial_quantity' => 'integer',
            'current_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function availableQuantity(): int
    {
        return max(0, (int) ($this->current_quantity ?? 0) - (int) ($this->reserved_quantity ?? 0));
    }

    public function daysUntilExpiration(): int
    {
        if ($this->expiration_date === null) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($this->expiration_date, false);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereDate('expiration_date', '>', now()->toDateString())
            ->whereColumn('current_quantity', '>', 'reserved_quantity');
    }

    public function scopeOrderByFefo(Builder $query): Builder
    {
        return $query
            ->orderBy('expiration_date')
            ->orderBy('id');
    }

    public function scopeAvailableForFefo(Builder $query): Builder
    {
        return $query
            ->available()
            ->orderByFefo();
    }

    public function scopeExpiringSoon(Builder $query, int $days = 60): Builder
    {
        return $query
            ->where('status', 'active')
            ->where('current_quantity', '>', 0)
            ->whereBetween('expiration_date', [
                now()->toDateString(),
                now()->addDays($days)->toDateString(),
            ])
            ->orderBy('expiration_date');
    }
}
