<?php

namespace App\Domain\Payment\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Payment\Models\CashSession;
use Illuminate\Foundation\Auth\User as Authenticatable;

class CashSessionPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('cash_session_view');
    }

    public function view(Authenticatable $user, CashSession $cashSession): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('cash_session_view');
    }

    public function open(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('cash_session_open');
    }

    public function close(Authenticatable $user, CashSession $cashSession): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('cash_session_close');
    }

    public function addEvent(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('cash_session_manage');
    }
}
