<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IndustryFloristPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'florist_arrangement_view',
            'florist_arrangement_manage',
            'florist_freshness_view',
            'florist_freshness_manage',
            'florist_subscription_view',
            'florist_subscription_manage',
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
                'florist_arrangement_view',
                'florist_freshness_view',
                'florist_subscription_view',
            ]);
        }
    }
}
