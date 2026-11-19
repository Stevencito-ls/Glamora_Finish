<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'event_type',
    'severity',
    'ip_address',
    'user_agent',
    'description',
    'metadata',
])]
class SecurityLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest();
    }
}
