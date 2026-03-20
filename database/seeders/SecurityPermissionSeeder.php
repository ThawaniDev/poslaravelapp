<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SecurityPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'security_audit_view',
            'security_device_view',
            'security_device_manage',
            'security_policy_manage',
            'security_remote_wipe',
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
                'security_audit_view',
                'security_device_view',
                'security_device_manage',
            ]);
        }
    }
}
