<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active && $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        if (! (bool) $user->is_active || ! $user->can('orders.view')) {
            return false;
        }

        return $this->isOperationsStaff($user) || $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_active && $user->can('orders.create');
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return (bool) $user->is_active && $user->can('orders.update_status');
    }

    public function dispatchFefo(User $user, Order $order): bool
    {
        return (bool) $user->is_active && $user->can('orders.dispatch_fefo');
    }

    public function cancel(User $user, Order $order): bool
    {
        return (bool) $user->is_active && $user->can('orders.cancel');
    }

    public function viewPayment(User $user, Order $order): bool
    {
        if (! (bool) $user->is_active || ! $user->can('payments.view')) {
            return false;
        }

        return $this->isOperationsStaff($user) || $order->user_id === $user->id;
    }

    public function verifyPayment(User $user, Order $order): bool
    {
        return (bool) $user->is_active && $user->can('payments.verify');
    }

    public function refundPayment(User $user, Order $order): bool
    {
        return (bool) $user->is_active && $user->can('payments.refund');
    }

    public function downloadDispatchGuide(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    private function isOperationsStaff(User $user): bool
    {
        return $user->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero']);
    }
}
