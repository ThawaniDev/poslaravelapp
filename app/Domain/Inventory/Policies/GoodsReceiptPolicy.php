<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Inventory\Models\GoodsReceipt;
use Illuminate\Foundation\Auth\User as Authenticatable;

class GoodsReceiptPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('goods_receipt_view');
    }

    public function view(Authenticatable $user, GoodsReceipt $goodsReceipt): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('goods_receipt_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('goods_receipt_create');
    }

    public function update(Authenticatable $user, GoodsReceipt $goodsReceipt): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('goods_receipt_update');
    }

    public function delete(Authenticatable $user, GoodsReceipt $goodsReceipt): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('goods_receipt_delete');
    }
}
