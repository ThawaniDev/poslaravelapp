<?php

namespace App\Domain\PosTerminal\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TransactionPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('transaction_view');
    }

    public function view(Authenticatable $user, Transaction $transaction): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('transaction_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('transaction_create');
    }

    public function void(Authenticatable $user, Transaction $transaction): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('transaction_void');
    }
}
