<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WholesaleProfile;

class WholesaleProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active && $user->can('wholesale.view');
    }

    public function view(User $user, WholesaleProfile $wholesaleProfile): bool
    {
        if (! (bool) $user->is_active || ! $user->can('wholesale.view')) {
            return false;
        }

        return $user->hasAnyRole(['SuperAdmin', 'Administrador'])
            || $wholesaleProfile->user_id === $user->id;
    }

    public function approve(User $user, WholesaleProfile $wholesaleProfile): bool
    {
        return (bool) $user->is_active && $user->can('wholesale.approve');
    }

    public function reject(User $user, WholesaleProfile $wholesaleProfile): bool
    {
        return (bool) $user->is_active && $user->can('wholesale.approve');
    }

    public function manageCredit(User $user, WholesaleProfile $wholesaleProfile): bool
    {
        return (bool) $user->is_active && $user->can('wholesale.manage_credit');
    }
}
