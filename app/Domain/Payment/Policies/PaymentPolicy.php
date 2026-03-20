<?php

namespace App\Domain\Payment\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Payment\Models\Payment;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PaymentPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('payment_view');
    }

    public function view(Authenticatable $user, Payment $payment): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('payment_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('payment_create');
    }

    public function refund(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('payment_refund');
    }
}
