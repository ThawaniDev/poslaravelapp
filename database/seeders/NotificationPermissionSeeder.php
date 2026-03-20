<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class NotificationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'notification_view',
            'notification_create',
            'notification_send_broadcast',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all notification permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets view + create
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'notification_view', 'notification_create',
            ]);
        }

        // Cashier gets view
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'notification_view',
            ]);
        }
    }
}
