<?php

namespace App\Domain\Customer\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Customer\Models\Customer;
use Illuminate\Foundation\Auth\User as Authenticatable;

class CustomerPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('customer_view');
    }

    public function view(Authenticatable $user, Customer $customer): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $customer->organization_id
            && $user->hasPermissionTo('customer_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('customer_create');
    }

    public function update(Authenticatable $user, Customer $customer): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $customer->organization_id
            && $user->hasPermissionTo('customer_update');
    }

    public function delete(Authenticatable $user, Customer $customer): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $customer->organization_id
            && $user->hasPermissionTo('customer_delete');
    }
}
