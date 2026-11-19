<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active && $user->can('catalog.view');
    }

    public function view(User $user, Product $product): bool
    {
        return (bool) $user->is_active && $user->can('catalog.view');
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_active && $user->can('catalog.create');
    }

    public function update(User $user, Product $product): bool
    {
        return (bool) $user->is_active && $user->can('catalog.edit');
    }

    public function delete(User $user, Product $product): bool
    {
        return (bool) $user->is_active && $user->can('catalog.delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return (bool) $user->is_active && $user->can('catalog.delete');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return (bool) $user->is_active && $user->hasRole('SuperAdmin');
    }
}
