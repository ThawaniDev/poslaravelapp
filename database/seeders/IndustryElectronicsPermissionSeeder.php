<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IndustryElectronicsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'electronics_imei_view',
            'electronics_imei_manage',
            'electronics_repair_view',
            'electronics_repair_manage',
            'electronics_trade_in_view',
            'electronics_trade_in_manage',
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
                'electronics_imei_view',
                'electronics_repair_view',
                'electronics_trade_in_view',
            ]);
        }
    }
}
