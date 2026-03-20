<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IndustryRestaurantPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'restaurant_table_view',
            'restaurant_table_manage',
            'restaurant_reservation_view',
            'restaurant_reservation_manage',
            'restaurant_kitchen_view',
            'restaurant_kitchen_manage',
            'restaurant_tab_view',
            'restaurant_tab_manage',
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
                'restaurant_table_view',
                'restaurant_reservation_view',
                'restaurant_reservation_manage',
                'restaurant_kitchen_view',
                'restaurant_kitchen_manage',
                'restaurant_tab_view',
                'restaurant_tab_manage',
            ]);
        }
    }
}
