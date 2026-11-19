<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'product_variant_id',
    'product_batch_id',
    'warehouse_location_id',
    'user_id',
    'movement_type',
    'quantity',
    'balance_after',
    'unit_cost',
    'reference_type',
    'reference_id',
    'notes',
])]
class InventoryMovement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
