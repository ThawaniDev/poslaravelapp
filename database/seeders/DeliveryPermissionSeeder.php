<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DeliveryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'delivery_config_view',
            'delivery_config_create',
            'delivery_config_update',
            'delivery_config_delete',
            'delivery_order_view',
            'delivery_order_update',
            'delivery_menu_sync',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo($permissions);
        }

        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'delivery_order_view',
                'delivery_order_update',
            ]);
        }
    }
}
