<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Enums\ShippingMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number',
    'tracking_code',
    'user_id',
    'customer_name',
    'customer_email',
    'customer_phone',
    'document_type',
    'document_number',
    'shipping_address',
    'shipping_district',
    'shipping_city',
    'shipping_method',
    'shipping_cost',
    'customer_tier',
    'status',
    'subtotal',
    'discount_amount',
    'coupon_code',
    'tax_igv',
    'total',
    'is_b2b',
    'notes',
    'fefo_dispatched_at',
    'delivered_at',
])]
class Order extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_method' => ShippingMethod::class,
            'shipping_cost' => 'decimal:2',
            'customer_tier' => CustomerTier::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_igv' => 'decimal:2',
            'total' => 'decimal:2',
            'is_b2b' => 'boolean',
            'fefo_dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_code', 'code');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function scopeStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeB2b(Builder $query): Builder
    {
        return $query->where('is_b2b', true);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest();
    }
}
