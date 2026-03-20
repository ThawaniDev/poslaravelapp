<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SupportPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'support_ticket_view',
            'support_ticket_create',
            'support_ticket_reply',
            'support_ticket_close',
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
                'support_ticket_view',
                'support_ticket_create',
                'support_ticket_reply',
            ]);
        }
    }
}
