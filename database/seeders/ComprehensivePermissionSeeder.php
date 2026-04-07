<?php

namespace Database\Seeders;

use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Domain\StaffManagement\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive permission & role seeder.
 *
 * Seeds all 167 system permissions (with Arabic translations), then creates
 * predefined roles for ALL stores that don't have them yet.
 *
 * Safe to re-run: permissions use updateOrCreate, roles skip existing stores.
 *
 * Usage:
 *   php artisan db:seed --class=ComprehensivePermissionSeeder
 *
 * Options (via --class arguments in tinker or direct call):
 *   - Default: seeds permissions + roles for ALL stores
 *   - Pass store_id to target a single store
 */
class ComprehensivePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('╔══════════════════════════════════════════════════╗');
        $this->command->info('║   Comprehensive Permission & Role Seeder        ║');
        $this->command->info('╚══════════════════════════════════════════════════╝');
        $this->command->info('');

        // ─── Step 1: Seed all permissions ─────────────────────────
        $this->command->info('Step 1: Seeding system permissions...');

        $permissionService = app(PermissionService::class);
        $count = $permissionService->seedAll();

        $totalDb = Permission::count();
        $pinCount = Permission::where('requires_pin', true)->count();
        $modules = Permission::distinct()->pluck('module')->count();

        $this->command->info("  ✓ {$count} permissions processed ({$totalDb} total in DB)");
        $this->command->info("  ✓ {$modules} modules, {$pinCount} PIN-protected");
        $this->command->info('');

        // ─── Step 2: Clean up stale permissions ───────────────────
        $this->command->info('Step 2: Cleaning up stale permissions...');

        $validNames = collect(PermissionService::ALL_PERMISSIONS)
            ->flatMap(fn ($perms) => collect($perms)->pluck('name'))
            ->all();

        $staleCount = Permission::whereNotIn('name', $validNames)->count();

        if ($staleCount > 0) {
            // Detach from roles first, then delete
            $staleIds = Permission::whereNotIn('name', $validNames)->pluck('id');
            DB::table('role_has_permissions')->whereIn('permission_id', $staleIds)->delete();
            Permission::whereIn('id', $staleIds)->delete();
            $this->command->warn("  ⚠ Removed {$staleCount} stale permissions no longer in the system.");
        } else {
            $this->command->info('  ✓ No stale permissions found.');
        }
        $this->command->info('');

        // ─── Step 3: Seed predefined roles for all stores ─────────
        $this->command->info('Step 3: Seeding predefined roles for stores...');

        $stores = Store::all();

        if ($stores->isEmpty()) {
            $this->command->warn('  ⚠ No stores found. Skipping role seeding.');
            $this->command->warn('    Run this seeder again after creating your first store.');
        } else {
            $allPermissions = Permission::all();
            $templates = RoleService::DEFAULT_ROLE_TEMPLATES;
            $storesSeeded = 0;
            $rolesCreated = 0;

            foreach ($stores as $store) {
                $existingRoles = Role::forStore($store->id)
                    ->where('is_predefined', true)
                    ->pluck('name')
                    ->all();

                $missingTemplates = collect($templates)
                    ->filter(fn ($t) => !in_array($t['name'], $existingRoles));

                if ($missingTemplates->isEmpty()) {
                    $this->command->line("  ─ {$store->name}: all roles exist, skipping.");
                    continue;
                }

                $storesSeeded++;

                foreach ($missingTemplates as $template) {
                    $role = Role::create([
                        'store_id'        => $store->id,
                        'name'            => $template['name'],
                        'display_name'    => $template['display_name'],
                        'display_name_ar' => $template['display_name_ar'] ?? null,
                        'guard_name'      => 'staff',
                        'is_predefined'   => true,
                        'description'     => $template['description'] ?? null,
                        'description_ar'  => $template['description_ar'] ?? null,
                    ]);

                    // Owner gets all permissions
                    if (in_array('*', $template['permissions'])) {
                        $role->permissions()->attach($allPermissions->pluck('id'));
                        $permCount = $allPermissions->count();
                    } else {
                        $permIds = $allPermissions->whereIn('name', $template['permissions'])->pluck('id');
                        $role->permissions()->attach($permIds);
                        $permCount = $permIds->count();

                        // Warn about missing permissions
                        $missing = count($template['permissions']) - $permCount;
                        if ($missing > 0) {
                            $this->command->warn("    ⚠ {$template['display_name']}: {$missing} permissions not found in DB");
                        }
                    }

                    $rolesCreated++;
                    $this->command->info("  ✓ {$store->name} → {$template['display_name']}: {$permCount} permissions");
                }
            }

            $this->command->info('');
            $this->command->info("  Summary: {$rolesCreated} roles created across {$storesSeeded} stores.");
        }

        // ─── Step 4: Verification ─────────────────────────────────
        $this->command->info('');
        $this->command->info('Step 4: Verification...');

        $finalPerms = Permission::count();
        $finalRoles = Role::count();
        $finalMappings = DB::table('role_has_permissions')->count();

        $this->command->info("  ✓ Permissions in DB: {$finalPerms}");
        $this->command->info("  ✓ Roles in DB: {$finalRoles}");
        $this->command->info("  ✓ Role ↔ Permission mappings: {$finalMappings}");
        $this->command->info('');
        $this->command->info('Done! ✓');
        $this->command->info('');
    }
}
