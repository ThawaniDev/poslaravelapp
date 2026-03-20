<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IndustryPharmacyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'pharmacy_drug_view',
            'pharmacy_drug_manage',
            'pharmacy_prescription_view',
            'pharmacy_prescription_manage',
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
                'pharmacy_drug_view',
                'pharmacy_prescription_view',
            ]);
        }
    }
}
