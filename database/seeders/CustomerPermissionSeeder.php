<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CustomerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Customer permissions
            'customer_view',
            'customer_create',
            'customer_update',
            'customer_delete',
            'customer_export',
            // Customer Group permissions
            'customer_group_view',
            'customer_group_create',
            'customer_group_update',
            'customer_group_delete',
            // Loyalty permissions
            'loyalty_view',
            'loyalty_manage',
            // Store Credit permissions
            'store_credit_view',
            'store_credit_manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all customer permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets most customer permissions
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'customer_view', 'customer_create', 'customer_update',
                'customer_group_view', 'customer_group_create', 'customer_group_update',
                'loyalty_view', 'loyalty_manage',
                'store_credit_view', 'store_credit_manage',
            ]);
        }

        // Cashier gets view + create customers, loyalty/credit operations
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'customer_view', 'customer_create',
                'customer_group_view',
                'loyalty_view', 'loyalty_manage',
                'store_credit_view', 'store_credit_manage',
            ]);
        }
    }
}
