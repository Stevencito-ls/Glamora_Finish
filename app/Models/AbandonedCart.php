<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_id',
    'user_id',
    'email',
    'cart_payload',
    'subtotal',
    'items_count',
    'last_activity_at',
    'reminder_count',
    'last_reminded_at',
    'recovered_at',
])]
class AbandonedCart extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cart_payload' => 'array',
            'subtotal' => 'decimal:2',
            'items_count' => 'integer',
            'last_activity_at' => 'datetime',
            'reminder_count' => 'integer',
            'last_reminded_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnrecovered(Builder $query): Builder
    {
        return $query->whereNull('recovered_at');
    }

    public function scopeEligibleForReminder(Builder $query, int $minutesIdle = 60): Builder
    {
        return $query
            ->whereNull('recovered_at')
            ->whereNotNull('email')
            ->where('items_count', '>', 0)
            ->where('last_activity_at', '<=', now()->subMinutes($minutesIdle));
    }
}
