<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PromotionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'promotion_view',
            'promotion_create',
            'promotion_update',
            'promotion_delete',
            'coupon_view',
            'coupon_create',
            'coupon_redeem',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all promotion permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets all except delete
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'promotion_view', 'promotion_create', 'promotion_update',
                'coupon_view', 'coupon_create', 'coupon_redeem',
            ]);
        }

        // Cashier gets view + redeem
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'promotion_view',
                'coupon_view', 'coupon_redeem',
            ]);
        }
    }
}
