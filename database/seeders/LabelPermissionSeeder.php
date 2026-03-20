<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LabelPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'label_view',
            'label_create',
            'label_update',
            'label_delete',
            'label_print',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all label permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets all label permissions
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo($permissions);
        }

        // Cashier gets view + print
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'label_view', 'label_print',
            ]);
        }

        // Inventory Clerk gets full label access
        $inventoryClerk = Role::where('name', 'inventory_clerk')->first();
        if ($inventoryClerk) {
            $inventoryClerk->givePermissionTo($permissions);
        }
    }
}
