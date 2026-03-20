<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Inventory\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PurchaseOrderPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('purchase_order_view');
    }

    public function view(Authenticatable $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $purchaseOrder->organization_id
            && $user->hasPermissionTo('purchase_order_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('purchase_order_create');
    }

    public function update(Authenticatable $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $purchaseOrder->organization_id
            && $user->hasPermissionTo('purchase_order_update');
    }

    public function delete(Authenticatable $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $purchaseOrder->organization_id
            && $user->hasPermissionTo('purchase_order_delete');
    }

    public function approve(Authenticatable $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $purchaseOrder->organization_id
            && $user->hasPermissionTo('purchase_order_approve');
    }
}
