<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ReportPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'report_sales',
            'report_products',
            'report_staff',
            'report_payments',
            'report_dashboard',
            'report_export',
            'report_inventory',
            'report_customers',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all report permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets all except export
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'report_sales', 'report_products', 'report_staff',
                'report_payments', 'report_dashboard',
                'report_inventory', 'report_customers',
            ]);
        }

        // Cashier gets dashboard only
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'report_dashboard',
            ]);
        }
    }
}
