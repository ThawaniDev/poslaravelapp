<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BackupPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'backup.view',
            'backup.create',
            'backup.restore',
            'backup.delete',
            'sync.view',
            'sync.manage',
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
                'backup.view',
                'backup.create',
                'sync.view',
                'sync.manage',
            ]);
        }

        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'sync.view',
            ]);
        }
    }
}
