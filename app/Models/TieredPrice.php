<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_variant_id',
    'customer_tier',
    'min_quantity',
    'max_quantity',
    'unit_price',
    'discount_percent',
])]
class TieredPrice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_tier' => CustomerTier::class,
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeForTier(Builder $query, CustomerTier $tier): Builder
    {
        return $query->where('customer_tier', $tier->value);
    }
}
