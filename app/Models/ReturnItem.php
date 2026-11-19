<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'return_id',
    'order_item_id',
    'product_variant_id',
    'product_batch_id',
    'quantity',
    'condition',
    'restocked',
    'inspection_notes',
])]
class ReturnItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'condition' => ReturnCondition::class,
            'restocked' => 'boolean',
        ];
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
