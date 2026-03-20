<?php

namespace App\Domain\Order\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Order\Models\SaleReturn;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SaleReturnPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_return');
    }

    public function view(Authenticatable $user, SaleReturn $saleReturn): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_return');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('order_return');
    }
}
