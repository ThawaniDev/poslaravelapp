<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IndustryBakeryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'bakery_recipe_view',
            'bakery_recipe_manage',
            'bakery_custom_order_view',
            'bakery_custom_order_manage',
            'bakery_production_view',
            'bakery_production_manage',
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
                'bakery_recipe_view',
                'bakery_custom_order_view',
                'bakery_custom_order_manage',
                'bakery_production_view',
            ]);
        }
    }
}
