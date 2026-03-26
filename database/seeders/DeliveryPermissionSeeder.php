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
            // Config management
            'delivery_config_view',
            'delivery_config_create',
            'delivery_config_update',
            'delivery_config_delete',
            'delivery_config_test_connection',

            // Order management
            'delivery_order_view',
            'delivery_order_update',
            'delivery_order_view_detail',

            // Menu sync
            'delivery_menu_sync',

            // Dashboard & platforms
            'delivery_stats_view',
            'delivery_platforms_view',

            // Filament admin aliases
            'delivery_view',
            'delivery_create',
            'delivery_update',
            'delivery_delete',
            'integrations.view',
            'integrations.manage',
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
                'delivery_order_view_detail',
                'delivery_stats_view',
            ]);
        }
    }
}
