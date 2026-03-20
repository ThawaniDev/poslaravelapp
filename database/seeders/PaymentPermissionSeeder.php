<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PaymentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Payment permissions
            'payment_view',
            'payment_create',
            'payment_refund',
            // Cash Session permissions
            'cash_session_view',
            'cash_session_open',
            'cash_session_close',
            'cash_session_manage',
            // Expense permissions
            'expense_view',
            'expense_create',
            // Gift Card permissions
            'gift_card_view',
            'gift_card_issue',
            'gift_card_redeem',
            'gift_card_deactivate',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all payment permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets all except gift card deactivate
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'payment_view', 'payment_create', 'payment_refund',
                'cash_session_view', 'cash_session_open', 'cash_session_close', 'cash_session_manage',
                'expense_view', 'expense_create',
                'gift_card_view', 'gift_card_issue', 'gift_card_redeem',
            ]);
        }

        // Cashier gets operational payment permissions
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'payment_view', 'payment_create',
                'cash_session_view', 'cash_session_open', 'cash_session_close',
                'expense_view', 'expense_create',
                'gift_card_view', 'gift_card_redeem',
            ]);
        }
    }
}
