<?php

namespace App\Domain\PosTerminal\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\PosTerminal\Models\PosSession;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PosSessionPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('pos_session_view');
    }

    public function view(Authenticatable $user, PosSession $session): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('pos_session_view');
    }

    public function open(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('pos_session_open');
    }

    public function close(Authenticatable $user, PosSession $session): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('pos_session_close');
    }
}
