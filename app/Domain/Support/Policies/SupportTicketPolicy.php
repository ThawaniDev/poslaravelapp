<?php

namespace App\Domain\Support\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Support\Models\SupportTicket;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SupportTicketPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('support_view');
    }

    public function view(Authenticatable $user, SupportTicket $ticket): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->store_id === $ticket->store_id
            && $user->hasPermissionTo('support_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('support_create');
    }

    public function update(Authenticatable $user, SupportTicket $ticket): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->store_id === $ticket->store_id
            && $user->hasPermissionTo('support_update');
    }
}
