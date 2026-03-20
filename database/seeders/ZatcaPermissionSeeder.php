<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ZatcaPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'zatca_invoice_view',
            'zatca_invoice_submit',
            'zatca_certificate_view',
            'zatca_certificate_enroll',
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
                'zatca_invoice_view',
                'zatca_invoice_submit',
                'zatca_certificate_view',
            ]);
        }
    }
}
