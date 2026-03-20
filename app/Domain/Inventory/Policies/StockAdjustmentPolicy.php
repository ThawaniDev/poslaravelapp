<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Inventory\Models\StockAdjustment;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StockAdjustmentPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_adjustment_view');
    }

    public function view(Authenticatable $user, StockAdjustment $stockAdjustment): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_adjustment_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_adjustment_create');
    }

    public function approve(Authenticatable $user, StockAdjustment $stockAdjustment): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_adjustment_approve');
    }
}
