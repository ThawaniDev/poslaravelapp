<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PosTerminalPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // POS Session permissions
            'pos_session_view',
            'pos_session_open',
            'pos_session_close',
            // Transaction permissions
            'transaction_view',
            'transaction_create',
            'transaction_void',
            // Held Cart permissions
            'held_cart_view',
            'held_cart_create',
            'held_cart_recall',
            'held_cart_delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all POS permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets all POS permissions
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo($permissions);
        }

        // Cashier gets operational POS permissions (no void)
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'pos_session_view', 'pos_session_open', 'pos_session_close',
                'transaction_view', 'transaction_create',
                'held_cart_view', 'held_cart_create', 'held_cart_recall', 'held_cart_delete',
            ]);
        }
    }
}
