<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InventoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Stock permissions
            'stock_view',
            'stock_update',
            // Goods Receipt permissions
            'goods_receipt_view',
            'goods_receipt_create',
            'goods_receipt_update',
            'goods_receipt_delete',
            // Purchase Order permissions
            'purchase_order_view',
            'purchase_order_create',
            'purchase_order_update',
            'purchase_order_delete',
            'purchase_order_approve',
            // Stock Adjustment permissions
            'stock_adjustment_view',
            'stock_adjustment_create',
            'stock_adjustment_approve',
            // Stock Transfer permissions
            'stock_transfer_view',
            'stock_transfer_create',
            'stock_transfer_update',
            'stock_transfer_approve',
            // Recipe permissions
            'recipe_view',
            'recipe_create',
            'recipe_update',
            'recipe_delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all inventory permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets most inventory permissions
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'stock_view', 'stock_update',
                'goods_receipt_view', 'goods_receipt_create', 'goods_receipt_update',
                'purchase_order_view', 'purchase_order_create', 'purchase_order_update',
                'stock_adjustment_view', 'stock_adjustment_create',
                'stock_transfer_view', 'stock_transfer_create', 'stock_transfer_update',
                'recipe_view', 'recipe_create', 'recipe_update',
            ]);
        }

        // Inventory Clerk gets operational inventory permissions
        $inventoryClerk = Role::where('name', 'inventory_clerk')->first();
        if ($inventoryClerk) {
            $inventoryClerk->givePermissionTo([
                'stock_view', 'stock_update',
                'goods_receipt_view', 'goods_receipt_create', 'goods_receipt_update',
                'purchase_order_view', 'purchase_order_create',
                'stock_adjustment_view', 'stock_adjustment_create',
                'stock_transfer_view', 'stock_transfer_create',
                'recipe_view',
            ]);
        }

        // Cashier gets view-only
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'stock_view',
                'recipe_view',
            ]);
        }
    }
}
