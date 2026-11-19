<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'company_name',
    'trade_name',
    'ruc',
    'business_type',
    'contact_name',
    'contact_phone',
    'contact_email',
    'fiscal_address',
    'district',
    'city',
    'requested_tier',
    'approved_tier',
    'credit_limit',
    'credit_used',
    'payment_terms_days',
    'status',
    'approved_by',
    'approved_at',
    'rejection_reason',
    'notes',
])]
class WholesaleProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_tier' => CustomerTier::class,
            'approved_tier' => CustomerTier::class,
            'credit_limit' => 'decimal:2',
            'credit_used' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function availableCredit(): float
    {
        return round(max(0.0, (float) $this->credit_limit - (float) $this->credit_used), 2);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
