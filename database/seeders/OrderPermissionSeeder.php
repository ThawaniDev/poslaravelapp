<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OrderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'order_view',
            'order_create',
            'order_manage',
            'order_void',
            'order_return',
            'order_exchange',
            'order_export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all order permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets all except export
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'order_view', 'order_create', 'order_manage',
                'order_void', 'order_return', 'order_exchange',
            ]);
        }

        // Cashier gets create, view, manage (no void/return without manager)
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'order_view', 'order_create', 'order_manage',
            ]);
        }
    }
}
