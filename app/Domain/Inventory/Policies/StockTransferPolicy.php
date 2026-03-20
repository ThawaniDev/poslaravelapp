<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Inventory\Models\StockTransfer;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StockTransferPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_transfer_view');
    }

    public function view(Authenticatable $user, StockTransfer $stockTransfer): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $stockTransfer->organization_id
            && $user->hasPermissionTo('stock_transfer_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('stock_transfer_create');
    }

    public function update(Authenticatable $user, StockTransfer $stockTransfer): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $stockTransfer->organization_id
            && $user->hasPermissionTo('stock_transfer_update');
    }

    public function approve(Authenticatable $user, StockTransfer $stockTransfer): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $stockTransfer->organization_id
            && $user->hasPermissionTo('stock_transfer_approve');
    }
}
