<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerTier;
use App\Support\CosmeticImageResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_id',
    'sku',
    'shade_name',
    'hex_color',
    'weight_grams',
    'volume_ml',
    'base_price',
    'wholesale_base_price',
    'cost_price',
    'barcode',
    'image_path',
    'is_default',
    'is_active',
])]
class ProductVariant extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:2',
            'volume_ml' => 'decimal:2',
            'base_price' => 'decimal:2',
            'wholesale_base_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tieredPrices(): HasMany
    {
        return $this->hasMany(TieredPrice::class)->orderBy('min_quantity');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function fefoBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class)->availableForFefo();
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

    public function availableStock(): int
    {
        $today = now()->toDateString();

        if ($this->relationLoaded('batches')) {
            return (int) $this->batches
                ->filter(fn (ProductBatch $batch): bool => $batch->status === 'active'
                    && $batch->expiration_date !== null
                    && $batch->expiration_date->toDateString() > $today
                    && $batch->current_quantity > $batch->reserved_quantity)
                ->sum(fn (ProductBatch $batch): int => $batch->availableQuantity());
        }

        return (int) $this->batches()
            ->available()
            ->reorder()
            ->selectRaw('COALESCE(SUM(current_quantity - reserved_quantity), 0) as aggregate_stock')
            ->value('aggregate_stock');
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->availableStock();
    }

    public function getImageUrlAttribute(): string
    {
        $rawPath = $this->image_path;

        if (($rawPath === null || trim($rawPath) === '') && $this->relationLoaded('product')) {
            $rawPath = $this->product?->main_image;
        }

        $key = $this->sku ?: ($this->relationLoaded('product') ? $this->product?->slug : null);

        return CosmeticImageResolver::resolve($rawPath, 'variant', $key);
    }

    public function priceForTier(CustomerTier $tier, int $quantity = 1): float
    {
        $quantity = max(1, $quantity);

        $tieredQuery = $this->relationLoaded('tieredPrices')
            ? $this->tieredPrices
                ->filter(fn (TieredPrice $tp): bool => $tp->customer_tier === $tier
                    && $tp->min_quantity <= $quantity
                    && ($tp->max_quantity === null || $tp->max_quantity >= $quantity))
                ->sortByDesc('min_quantity')
                ->first()
            : $this->tieredPrices()
                ->where('customer_tier', $tier->value)
                ->where('min_quantity', '<=', $quantity)
                ->where(function (Builder $q) use ($quantity): void {
                    $q->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity);
                })
                ->reorder('min_quantity', 'desc')
                ->first();

        if ($tieredQuery !== null) {
            return (float) $tieredQuery->unit_price;
        }

        if ($tier === CustomerTier::Minorista) {
            return (float) $this->base_price;
        }

        // Si no hay regla explícita en tiered_prices, el precio mayorista base actúa como techo máximo.
        $discounted = round((float) $this->base_price * (1 - ($tier->discountPercentage() / 100)), 2);
        $wholesaleBase = (float) $this->wholesale_base_price;

        return $wholesaleBase > 0
            ? min($wholesaleBase, $discounted)
            : $discounted;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
