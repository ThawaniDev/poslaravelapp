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
 * Seeds all 167 system permissions (with Arabic translations), REMOVES all old
 * predefined roles, then creates 16 new predefined roles for ALL stores.
 *
 * Safe to re-run: permissions use updateOrCreate, old roles are fully replaced.
 *
 * Usage:
 *   php artisan db:seed --class=ComprehensivePermissionSeeder
 *
 * 16 Predefined Roles:
 * Organization-level (7): owner, manager, accountant, chain_manager, inventory_manager, viewer, sales_manager
 * Branch-level (9): branch_manager, branch_accountant, branch_chain_manager, branch_inventory_manager,
 *                    branch_kitchen_staff, senior_cashier, cashier, branch_viewer, branch_sales_manager
 */
class ComprehensivePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('╔══════════════════════════════════════════════════╗');
        $this->command->info('║   Comprehensive Permission & Role Seeder v2     ║');
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
            $staleIds = Permission::whereNotIn('name', $validNames)->pluck('id');
            DB::table('role_has_permissions')->whereIn('permission_id', $staleIds)->delete();
            Permission::whereIn('id', $staleIds)->delete();
            $this->command->warn("  ⚠ Removed {$staleCount} stale permissions no longer in the system.");
        } else {
            $this->command->info('  ✓ No stale permissions found.');
        }
        $this->command->info('');

        // ─── Step 3: Remove ALL old predefined roles ──────────────
        $this->command->info('Step 3: Removing old predefined roles...');

        $oldRoles = Role::where('is_predefined', true)->get();
        $oldCount = $oldRoles->count();

        if ($oldCount > 0) {
            foreach ($oldRoles as $oldRole) {
                // Detach permissions
                $oldRole->permissions()->detach();
                // Remove user assignments to this role
                DB::table('model_has_roles')->where('role_id', $oldRole->id)->delete();
                // Remove staff branch assignments referencing this role
                DB::table('staff_branch_assignments')->where('role_id', $oldRole->id)->delete();
                $oldRole->delete();
            }
            $this->command->warn("  ⚠ Removed {$oldCount} old predefined roles (and their user assignments).");
            $this->command->warn('    NOTE: Users will need to be reassigned to new roles after seeding.');
        } else {
            $this->command->info('  ✓ No old predefined roles to remove.');
        }
        $this->command->info('');

        // ─── Step 4: Seed new predefined roles for all stores ─────
        $this->command->info('Step 4: Seeding 16 new predefined roles for stores...');

        $stores = Store::all();

        if ($stores->isEmpty()) {
            $this->command->warn('  ⚠ No stores found. Skipping role seeding.');
            $this->command->warn('    Run this seeder again after creating your first store.');
        } else {
            $allPermissions = Permission::all();
            $templates = RoleService::DEFAULT_ROLE_TEMPLATES;
            $rolesCreated = 0;

            foreach ($stores as $store) {
                $this->command->info("  ─ Store: {$store->name} ({$store->id})");

                foreach ($templates as $template) {
                    $role = Role::create([
                        'store_id'        => $store->id,
                        'name'            => $template['name'],
                        'display_name'    => $template['display_name'],
                        'display_name_ar' => $template['display_name_ar'] ?? null,
                        'guard_name'      => 'staff',
                        'is_predefined'   => true,
                        'scope'           => $template['scope'] ?? 'branch',
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

                        $missing = count($template['permissions']) - $permCount;
                        if ($missing > 0) {
                            $this->command->warn("    ⚠ {$template['display_name']}: {$missing} permissions not found in DB");
                        }
                    }

                    $scope = $template['scope'] ?? 'branch';
                    $this->command->line("    ✓ {$template['display_name']} ({$scope}): {$permCount} permissions");
                    $rolesCreated++;
                }
            }

            $this->command->info('');
            $this->command->info("  Summary: {$rolesCreated} roles created across {$stores->count()} stores.");
        }

        // ─── Step 5: Auto-assign owner role to store owners ──────
        $this->command->info('');
        $this->command->info('Step 5: Auto-assigning owner roles...');

        $ownerAssignments = 0;
        foreach (Store::all() as $store) {
            // Find users with store_id matching this store and role = 'owner'
            $ownerUsers = \App\Domain\Auth\Models\User::where('store_id', $store->id)
                ->where('role', 'owner')
                ->get();

            $ownerRole = Role::where('store_id', $store->id)
                ->where('name', 'owner')
                ->where('is_predefined', true)
                ->first();

            if ($ownerRole && $ownerUsers->isNotEmpty()) {
                foreach ($ownerUsers as $ownerUser) {
                    DB::table('model_has_roles')->updateOrInsert(
                        [
                            'role_id'    => $ownerRole->id,
                            'model_id'   => $ownerUser->id,
                            'model_type' => get_class($ownerUser),
                        ],
                    );
                    $ownerAssignments++;
                    $this->command->info("  ✓ Assigned owner role to {$ownerUser->email} in {$store->name}");
                }
            }
        }

        if ($ownerAssignments === 0) {
            $this->command->info('  ─ No owner users found to auto-assign.');
        }

        // ─── Step 6: Verification ─────────────────────────────────
        $this->command->info('');
        $this->command->info('Step 6: Verification...');

        $finalPerms = Permission::count();
        $finalRoles = Role::count();
        $finalPredefined = Role::where('is_predefined', true)->count();
        $finalMappings = DB::table('role_has_permissions')->count();
        $finalAssignments = DB::table('model_has_roles')->count();

        $orgRoles = Role::where('is_predefined', true)->where('scope', 'organization')->count();
        $branchRoles = Role::where('is_predefined', true)->where('scope', 'branch')->count();

        $this->command->info("  ✓ Permissions in DB: {$finalPerms}");
        $this->command->info("  ✓ Roles in DB: {$finalRoles} ({$finalPredefined} predefined)");
        $this->command->info("  ✓ Organization-scoped roles: {$orgRoles}");
        $this->command->info("  ✓ Branch-scoped roles: {$branchRoles}");
        $this->command->info("  ✓ Role ↔ Permission mappings: {$finalMappings}");
        $this->command->info("  ✓ User ↔ Role assignments: {$finalAssignments}");
        $this->command->info('');
        $this->command->info('Done! Users need to be assigned to their new roles.');
        $this->command->info('');
    }
}
