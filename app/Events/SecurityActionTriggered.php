<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class SecurityActionTriggered
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly ?User $user,
        public readonly string $eventType,
        public readonly string $severity,
        public readonly string $description,
        public readonly array $metadata = [],
    ) {}
}
