<?php

namespace Database\Seeders;

use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds cashier gamification permissions and assigns them to existing predefined roles.
 *
 * Safe to re-run: uses firstOrCreate for permissions, checks before attaching.
 *
 * Usage:
 *   php artisan db:seed --class=CashierGamificationPermissionSeeder
 */
class CashierGamificationPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        ['name' => 'cashier_performance.view_leaderboard', 'display_name' => 'View Cashier Leaderboard',    'display_name_ar' => 'عرض لوحة متصدري الصرافين',  'description' => 'View cashier performance leaderboard and history',           'description_ar' => 'عرض لوحة متصدري أداء الصرافين والسجل'],
        ['name' => 'cashier_performance.view_badges',      'display_name' => 'View Cashier Badges',         'display_name_ar' => 'عرض شارات الصرافين',         'description' => 'View cashier badge definitions and awards',                 'description_ar' => 'عرض تعريفات شارات الصرافين والجوائز'],
        ['name' => 'cashier_performance.manage_badges',    'display_name' => 'Manage Cashier Badges',       'display_name_ar' => 'إدارة شارات الصرافين',       'description' => 'Create, edit, delete cashier badge definitions',            'description_ar' => 'إنشاء وتعديل وحذف تعريفات شارات الصرافين'],
        ['name' => 'cashier_performance.view_anomalies',   'display_name' => 'View Cashier Anomalies',      'display_name_ar' => 'عرض الحالات الشاذة للصرافين', 'description' => 'View anomaly alerts and risk scores for cashiers',          'description_ar' => 'عرض تنبيهات الحالات الشاذة ودرجات المخاطر للصرافين'],
        ['name' => 'cashier_performance.view_reports',     'display_name' => 'View Shift Reports',          'display_name_ar' => 'عرض تقارير الورديات',        'description' => 'View cashier shift-end report cards',                       'description_ar' => 'عرض بطاقات تقارير نهاية وردية الصراف'],
        ['name' => 'cashier_performance.manage_settings',  'display_name' => 'Manage Gamification Settings', 'display_name_ar' => 'إدارة إعدادات التحفيز',      'description' => 'Configure gamification settings, weights, and thresholds',  'description_ar' => 'تكوين إعدادات التحفيز والأوزان والحدود'],
    ];

    /** View-only subset for branch_viewer and branch_sales_manager. */
    private const VIEW_ONLY = [
        'cashier_performance.view_leaderboard',
        'cashier_performance.view_badges',
        'cashier_performance.view_anomalies',
        'cashier_performance.view_reports',
    ];

    public function run(): void
    {
        $this->command->info('Seeding cashier gamification permissions...');

        // 1. Create permissions
        foreach (self::PERMISSIONS as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'sanctum'],
                [
                    'module'          => 'cashier_performance',
                    'display_name'    => $perm['display_name'],
                    'display_name_ar' => $perm['display_name_ar'],
                    'description'     => $perm['description'],
                    'description_ar'  => $perm['description_ar'],
                ],
            );
        }
        $this->command->info('  ✓ 6 permissions ensured.');

        // 2. Assign to predefined roles
        $allPermNames = array_column(self::PERMISSIONS, 'name');
        $viewPermNames = self::VIEW_ONLY;
        $allPermIds = Permission::whereIn('name', $allPermNames)->pluck('id');
        $viewPermIds = Permission::whereIn('name', $viewPermNames)->pluck('id');

        // Roles that get ALL 6 permissions
        $fullAccessRoles = Role::where('is_predefined', true)
            ->whereIn('name', ['owner', 'manager', 'branch_manager'])
            ->get();

        // Roles that get view-only permissions
        $viewOnlyRoles = Role::where('is_predefined', true)
            ->whereIn('name', ['branch_viewer', 'branch_sales_manager', 'senior_cashier'])
            ->get();

        $updated = 0;

        foreach ($fullAccessRoles as $role) {
            $existing = DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $allPermIds)
                ->pluck('permission_id');
            $toAttach = $allPermIds->diff($existing);
            if ($toAttach->isNotEmpty()) {
                $role->permissions()->attach($toAttach);
                $updated++;
            }
            $this->command->info("  ✓ {$role->display_name} (store {$role->store_id}): full access");
        }

        foreach ($viewOnlyRoles as $role) {
            $existing = DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $viewPermIds)
                ->pluck('permission_id');
            $toAttach = $viewPermIds->diff($existing);
            if ($toAttach->isNotEmpty()) {
                $role->permissions()->attach($toAttach);
                $updated++;
            }
            $this->command->info("  ✓ {$role->display_name} (store {$role->store_id}): view only");
        }

        $this->command->info("  Done. {$updated} roles updated.");
    }
}
