<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrderReturn;
use App\Models\User;

class OrderReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active && $user->can('returns.view');
    }

    public function view(User $user, OrderReturn $orderReturn): bool
    {
        if (! (bool) $user->is_active || ! $user->can('returns.view')) {
            return false;
        }

        return $user->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero'])
            || $orderReturn->user_id === $user->id;
    }

    public function inspect(User $user, OrderReturn $orderReturn): bool
    {
        return (bool) $user->is_active && $user->can('returns.inspect');
    }

    public function resolve(User $user, OrderReturn $orderReturn): bool
    {
        return (bool) $user->is_active && $user->can('returns.resolve');
    }
}
