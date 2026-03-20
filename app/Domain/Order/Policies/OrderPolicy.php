<?php

namespace App\Domain\Order\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Auth\User as Authenticatable;

class OrderPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_view');
    }

    public function view(Authenticatable $user, Order $order): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_create');
    }

    public function updateStatus(Authenticatable $user, Order $order): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_manage');
    }

    public function void(Authenticatable $user, Order $order): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_void');
    }

    public function return(Authenticatable $user, Order $order): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_return');
    }

    public function exchange(Authenticatable $user, Order $order): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_exchange');
    }
}
