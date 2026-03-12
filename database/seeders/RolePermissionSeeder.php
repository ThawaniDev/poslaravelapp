<?php

namespace Database\Seeders;

use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Domain\StaffManagement\Services\RoleService;
use Illuminate\Database\Seeder;

/**
 * Seeds all system permissions and creates predefined roles for
 * the demo store created by UserSeeder.
 *
 * Run: php artisan db:seed --class=RolePermissionSeeder
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding permissions...');

        // 1. Seed all system permissions
        $permissionService = app(PermissionService::class);
        $permissionService->seedAll();

        $totalPerms = Permission::count();
        $this->command->info("  → {$totalPerms} permissions seeded.");

        // 2. Seed predefined roles for the demo store (from UserSeeder)
        $demoStore = \App\Domain\Core\Models\Store::where('name', 'Thawani Demo Store')->first();

        if ($demoStore) {
            $this->command->info("Seeding predefined roles for demo store ({$demoStore->id})...");

            $roleService = app(RoleService::class);

            // Only seed if no roles exist yet for this store
            if (Role::forStore($demoStore->id)->count() === 0) {
                $this->seedRolesForStore($demoStore->id, $roleService);
            } else {
                $this->command->info('  → Roles already exist for this store. Skipping.');
            }
        } else {
            $this->command->warn('Demo store not found. Run UserSeeder first.');
        }
    }

    private function seedRolesForStore(string $storeId, RoleService $roleService): void
    {
        $allPermissions = Permission::all();

        foreach (RoleService::DEFAULT_ROLE_TEMPLATES as $template) {
            $role = Role::create([
                'store_id'      => $storeId,
                'name'          => $template['name'],
                'display_name'  => $template['display_name'],
                'guard_name'    => 'staff',
                'is_predefined' => true,
                'description'   => $template['description'] ?? null,
            ]);

            // Owner gets all permissions
            if (in_array('*', $template['permissions'])) {
                $role->permissions()->attach($allPermissions->pluck('id'));
                $this->command->info("  → {$template['display_name']}: ALL {$allPermissions->count()} permissions");
            } else {
                $permIds = $allPermissions
                    ->whereIn('name', $template['permissions'])
                    ->pluck('id');
                $role->permissions()->attach($permIds);
                $this->command->info("  → {$template['display_name']}: {$permIds->count()} permissions");
            }
        }
    }
}
