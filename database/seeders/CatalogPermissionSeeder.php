<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CatalogPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Product permissions
            'product_view',
            'product_create',
            'product_update',
            'product_delete',
            'product_export',
            'product_import',
            // Category permissions
            'category_view',
            'category_create',
            'category_update',
            'category_delete',
            // Supplier permissions
            'supplier_view',
            'supplier_create',
            'supplier_update',
            'supplier_delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Assign all catalog permissions to Owner role
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Assign view + create + update to Branch Manager
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'product_view', 'product_create', 'product_update',
                'category_view', 'category_create', 'category_update',
                'supplier_view', 'supplier_create', 'supplier_update',
            ]);
        }

        // Assign view-only to Cashier
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'product_view', 'category_view', 'supplier_view',
            ]);
        }

        // Assign view + update to Inventory Clerk
        $inventoryClerk = Role::where('name', 'inventory_clerk')->first();
        if ($inventoryClerk) {
            $inventoryClerk->givePermissionTo([
                'product_view', 'product_create', 'product_update', 'product_export',
                'category_view',
                'supplier_view', 'supplier_create', 'supplier_update',
            ]);
        }
    }
}
