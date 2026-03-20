<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IndustryJewelryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'jewelry_detail_view',
            'jewelry_detail_manage',
            'jewelry_rate_view',
            'jewelry_rate_manage',
            'jewelry_buyback_view',
            'jewelry_buyback_manage',
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
                'jewelry_detail_view',
                'jewelry_rate_view',
                'jewelry_buyback_view',
            ]);
        }
    }
}
