<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Staff CRUD
            'staff_view',
            'staff_create',
            'staff_update',
            'staff_delete',
            // Attendance
            'attendance_view',
            'attendance_manage',
            'attendance_clock',
            // Shifts
            'shift_view',
            'shift_manage',
            // Roles
            'role_view',
            'role_manage',
            // Commissions
            'commission_view',
            'commission_manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
            );
        }

        // Owner gets all staff permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Branch Manager gets all except delete and role manage
        $branchManager = Role::where('name', 'branch_manager')->first();
        if ($branchManager) {
            $branchManager->givePermissionTo([
                'staff_view', 'staff_create', 'staff_update',
                'attendance_view', 'attendance_manage', 'attendance_clock',
                'shift_view', 'shift_manage',
                'role_view',
                'commission_view', 'commission_manage',
            ]);
        }

        // Cashier gets attendance clock and view
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'staff_view',
                'attendance_view', 'attendance_clock',
                'shift_view',
                'role_view',
                'commission_view',
            ]);
        }
    }
}
