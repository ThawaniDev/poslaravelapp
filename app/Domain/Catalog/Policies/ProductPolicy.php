<?php

namespace App\Domain\Catalog\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ProductPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('product_view');
    }

    public function view(Authenticatable $user, Product $product): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $product->organization_id
            && $user->hasPermissionTo('product_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('product_create');
    }

    public function update(Authenticatable $user, Product $product): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $product->organization_id
            && $user->hasPermissionTo('product_update');
    }

    public function delete(Authenticatable $user, Product $product): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $product->organization_id
            && $user->hasPermissionTo('product_delete');
    }

    public function export(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('product_export');
    }

    public function import(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('product_import');
    }
}
