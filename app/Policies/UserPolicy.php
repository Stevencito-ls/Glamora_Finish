<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active && ($user->hasRole('SuperAdmin') || $user->can('users.manage'));
    }

    public function view(User $user, User $target): bool
    {
        if (! (bool) $user->is_active || ! $user->can('users.manage')) {
            return false;
        }

        if ($target->hasRole('SuperAdmin') && ! $user->hasRole('SuperAdmin')) {
            return false;
        }

        return true;
    }

    public function create(User $user, ?string $targetRole = null): bool
    {
        if (! (bool) $user->is_active || ! $user->can('users.manage')) {
            return false;
        }

        if ($targetRole !== null && strcasecmp(trim($targetRole), 'SuperAdmin') === 0 && ! $user->hasRole('SuperAdmin')) {
            return false;
        }

        return true;
    }

    public function update(User $user, User $target): bool
    {
        if (! (bool) $user->is_active || ! $user->can('users.manage')) {
            return false;
        }

        if ($target->hasRole('SuperAdmin') && ! $user->hasRole('SuperAdmin')) {
            return false;
        }

        return true;
    }

    public function assignRole(User $user, User $target, string $roleName): bool
    {
        if (! (bool) $user->is_active || ! $user->can('users.manage')) {
            return false;
        }

        $isSuperAdminTargetRole = strcasecmp(trim($roleName), 'SuperAdmin') === 0;

        if (($isSuperAdminTargetRole || $target->hasRole('SuperAdmin')) && ! $user->hasRole('SuperAdmin')) {
            return false;
        }

        if ($user->id === $target->id && $target->hasRole('SuperAdmin') && ! $isSuperAdminTargetRole) {
            return false;
        }

        return true;
    }

    public function toggleActive(User $user, User $target): bool
    {
        if (! (bool) $user->is_active || ! $user->can('users.manage')) {
            return false;
        }

        if ($user->id === $target->id) {
            return false;
        }

        if ($target->hasRole('SuperAdmin') && ! $user->hasRole('SuperAdmin')) {
            return false;
        }

        return true;
    }

    public function resetTwoFactor(User $user, User $target): bool
    {
        if (! (bool) $user->is_active || ! $user->can('users.manage')) {
            return false;
        }

        if ($target->hasRole('SuperAdmin') && ! $user->hasRole('SuperAdmin')) {
            return false;
        }

        return true;
    }

    public function delete(User $user, User $target): bool
    {
        if (! (bool) $user->is_active || ! $user->can('users.manage')) {
            return false;
        }

        if ($user->id === $target->id) {
            return false;
        }

        if ($target->hasRole('SuperAdmin') && ! $user->hasRole('SuperAdmin')) {
            return false;
        }

        return true;
    }
}
