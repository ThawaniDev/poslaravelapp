<?php

namespace App\Domain\Catalog\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Supplier;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SupplierPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('supplier_view');
    }

    public function view(Authenticatable $user, Supplier $supplier): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $supplier->organization_id
            && $user->hasPermissionTo('supplier_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('supplier_create');
    }

    public function update(Authenticatable $user, Supplier $supplier): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $supplier->organization_id
            && $user->hasPermissionTo('supplier_update');
    }

    public function delete(Authenticatable $user, Supplier $supplier): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $supplier->organization_id
            && $user->hasPermissionTo('supplier_delete');
    }
}
