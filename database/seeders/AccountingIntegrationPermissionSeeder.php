<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccountingIntegrationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'accounting_integration_view',
            'accounting_integration_connect',
            'accounting_integration_export',
            'accounting_integration_mapping',
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
            $branchManager->givePermissionTo([
                'accounting_integration_view',
                'accounting_integration_export',
            ]);
        }
    }
}
