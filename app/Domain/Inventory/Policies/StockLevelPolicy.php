<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StockLevelPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_view');
    }

    public function view(Authenticatable $user, StockLevel $stockLevel): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_view');
    }

    public function update(Authenticatable $user, StockLevel $stockLevel): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_update');
    }
}
