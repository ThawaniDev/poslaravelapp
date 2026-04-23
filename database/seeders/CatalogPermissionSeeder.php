<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds catalog permissions.
 *
 * Permission names use dot-notation that matches routes/api/catalog.php
 * `permission:` middleware declarations exactly.
 */
class CatalogPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'products.view',
            'products.manage',
            'products.manage_pricing',
            'products.manage_categories',
            'products.manage_suppliers',
            'products.import',
            'products.export',
            'inventory.view',
            'reports.view_margin',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        if ($owner = Role::where('name', 'owner')->first()) {
            $owner->givePermissionTo($permissions);
        }

        if ($branchManager = Role::where('name', 'branch_manager')->first()) {
            $branchManager->givePermissionTo([
                'products.view',
                'products.manage',
                'products.manage_pricing',
                'products.manage_categories',
                'products.manage_suppliers',
                'products.import',
                'products.export',
                'inventory.view',
                'reports.view_margin',
            ]);
        }

        if ($inventoryClerk = Role::where('name', 'inventory_clerk')->first()) {
            $inventoryClerk->givePermissionTo([
                'products.view',
                'products.manage',
                'products.manage_categories',
                'products.manage_suppliers',
                'products.import',
                'products.export',
                'inventory.view',
            ]);
        }

        if ($cashier = Role::where('name', 'cashier')->first()) {
            $cashier->givePermissionTo([
                'products.view',
                'inventory.view',
            ]);
        }
    }
}
