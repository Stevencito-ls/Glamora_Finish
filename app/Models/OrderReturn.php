<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'rma_number',
    'order_id',
    'user_id',
    'requester_name',
    'requester_email',
    'reason',
    'status',
    'inspector_id',
    'inspected_at',
    'refund_amount',
    'resolution_notes',
])]
class OrderReturn extends Model
{
    protected $table = 'returns';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'refund_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function scopePendingInspection(Builder $query): Builder
    {
        return $query->whereIn('status', ['solicitado', 'en_revision']);
    }
}
