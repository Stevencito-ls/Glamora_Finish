<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

#[Fillable([
    'code',
    'description',
    'discount_type',
    'discount_value',
    'min_order_amount',
    'max_uses',
    'used_count',
    'applicable_tier',
    'starts_at',
    'expires_at',
    'is_active',
])]
class Coupon extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'applicable_tier' => CustomerTier::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValidNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
            ->where(fn (Builder $q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    public function isValidFor(float|int|string $subtotal, ?CustomerTier $tier = null): bool
    {
        if (is_string($subtotal) && ! is_numeric($subtotal)) {
            throw new InvalidArgumentException('El subtotal debe ser un valor numérico.');
        }

        $numericSubtotal = (float) $subtotal;

        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isBefore($now)) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($numericSubtotal < (float) $this->min_order_amount) {
            return false;
        }

        if ($this->applicable_tier !== null && $this->applicable_tier !== $tier) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float|int|string $subtotal): float
    {
        if (is_string($subtotal) && ! is_numeric($subtotal)) {
            throw new InvalidArgumentException('El subtotal debe ser un valor numérico.');
        }

        $numericSubtotal = (float) $subtotal;

        if ($numericSubtotal <= 0.0) {
            return 0.0;
        }

        if ($this->discount_type === 'fixed') {
            return round(min($numericSubtotal, max(0.0, (float) $this->discount_value)), 2);
        }

        return round(min($numericSubtotal, max(0.0, $numericSubtotal * ((float) $this->discount_value / 100))), 2);
    }
}
