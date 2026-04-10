<?php

namespace Database\Seeders;

use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Seeds Wameed AI admin (platform) permissions and assigns them to roles.
 *
 * Run:  php artisan db:seed --class=AdminWameedAIPermissionSeeder
 */
class AdminWameedAIPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Wameed AI admin permissions...');

        // ── 1. Create admin permissions ─────────────────────────────
        $permissions = [
            ['name' => 'wameed_ai.view',   'group' => 'wameed_ai', 'description' => 'View AI usage analytics and platform-wide AI metrics'],
            ['name' => 'wameed_ai.manage', 'group' => 'wameed_ai', 'description' => 'Manage AI provider configurations and feature definitions'],
            ['name' => 'wameed_ai.logs',   'group' => 'wameed_ai', 'description' => 'View AI usage logs and audit trails'],
        ];

        foreach ($permissions as $perm) {
            AdminPermission::firstOrCreate(
                ['name' => $perm['name']],
                $perm,
            );
        }

        $this->command->info('  ✓ 3 wameed_ai admin permissions created');

        // ── 2. Assign to super_admin (ALL permissions) ──────────────
        $superAdmin = AdminRole::where('slug', 'super_admin')->first();
        if ($superAdmin) {
            $allPermIds = AdminPermission::pluck('id');
            foreach ($allPermIds as $permId) {
                AdminRolePermission::firstOrCreate([
                    'admin_role_id' => $superAdmin->id,
                    'admin_permission_id' => $permId,
                ]);
            }
            $count = AdminRolePermission::where('admin_role_id', $superAdmin->id)->count();
            $this->command->info("  ✓ super_admin: {$count} permissions (all)");
        }

        // ── 3. Assign view + manage to platform_manager ─────────────
        $platformManager = AdminRole::where('slug', 'platform_manager')->first();
        if ($platformManager) {
            $aiPerms = AdminPermission::whereIn('name', ['wameed_ai.view', 'wameed_ai.manage'])->pluck('id');
            foreach ($aiPerms as $permId) {
                AdminRolePermission::firstOrCreate([
                    'admin_role_id' => $platformManager->id,
                    'admin_permission_id' => $permId,
                ]);
            }
            $count = AdminRolePermission::where('admin_role_id', $platformManager->id)->count();
            $this->command->info("  ✓ platform_manager: {$count} permissions (+wameed_ai.view, wameed_ai.manage)");
        }

        // ── 4. Assign view to support_agent, finance_admin, viewer ──
        $viewPerm = AdminPermission::where('name', 'wameed_ai.view')->first();
        if ($viewPerm) {
            foreach (['support_agent', 'finance_admin', 'viewer'] as $roleSlug) {
                $role = AdminRole::where('slug', $roleSlug)->first();
                if ($role) {
                    AdminRolePermission::firstOrCreate([
                        'admin_role_id' => $role->id,
                        'admin_permission_id' => $viewPerm->id,
                    ]);
                    $this->command->info("  ✓ {$roleSlug}: +wameed_ai.view");
                }
            }
        }

        // ── 5. Clear admin permission caches ────────────────────────
        $this->command->info('Clearing admin permission caches...');
        $pattern = 'admin_user:*';
        // Clear all cached admin permission/role data
        try {
            Cache::flush();
            $this->command->info('  ✓ Cache cleared');
        } catch (\Exception $e) {
            $this->command->warn("  ⚠ Could not flush cache: {$e->getMessage()}");
        }

        $this->command->info('Done! Wameed AI admin permissions seeded.');
    }
}
