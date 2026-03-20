<?php

namespace App\Domain\Report\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ReportPolicy
{
    public function viewSales(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('report_sales');
    }

    public function viewProducts(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('report_products');
    }

    public function viewStaff(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('report_staff');
    }

    public function viewPayments(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('report_payments');
    }

    public function viewDashboard(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('report_dashboard');
    }

    public function export(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('report_export');
    }
}
