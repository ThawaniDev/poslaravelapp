<?php

namespace Database\Seeders;

use App\Domain\StaffManagement\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds RoleService::DEFAULT_ROLE_TEMPLATES (16 predefined roles) per store
 * AND attaches their permissions via role_has_permissions.
 *
 * - Updates existing predefined roles to guard='staff' to match permissions guard.
 * - Idempotent — safe to re-run.
 *
 * Run: php artisan db:seed --class=PredefinedRolePermissionsSeeder --force
 */
class PredefinedRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $stores = DB::table('stores')->pluck('id');
        if ($stores->isEmpty()) {
            $this->command->warn('No stores — nothing to seed.');
            return;
        }

        // Build name → id map for staff-guard permissions (the canonical runtime set).
        $permMap = DB::table('permissions')
            ->where('guard_name', 'staff')
            ->pluck('id', 'name')
            ->all();
        $allPermIds = array_values($permMap);
        $this->command->info('Loaded ' . count($permMap) . ' staff-guard permissions.');

        $rolesUpserted = 0;
        $linksInserted = 0;
        $linksRemoved = 0;

        foreach ($stores as $storeId) {
            foreach (RoleService::DEFAULT_ROLE_TEMPLATES as $tpl) {
                $existing = DB::table('roles')
                    ->where('store_id', $storeId)
                    ->where('name', $tpl['name'])
                    ->first();

                $payload = [
                    'guard_name'      => 'staff',
                    'display_name'    => $tpl['display_name'],
                    'display_name_ar' => $tpl['display_name_ar'] ?? null,
                    'description'     => $tpl['description'] ?? null,
                    'description_ar'  => $tpl['description_ar'] ?? null,
                    'is_predefined'   => true,
                    'scope'           => $tpl['scope'] ?? 'branch',
                    'updated_at'      => now(),
                ];

                if ($existing) {
                    DB::table('roles')->where('id', $existing->id)->update($payload);
                    $roleId = $existing->id;
                } else {
                    $roleId = DB::table('roles')->insertGetId(array_merge($payload, [
                        'store_id'   => $storeId,
                        'name'       => $tpl['name'],
                        'created_at' => now(),
                    ]));
                }
                $rolesUpserted++;

                // Resolve permission IDs.
                if (in_array('*', $tpl['permissions'], true)) {
                    $targetIds = $allPermIds;
                } else {
                    $targetIds = [];
                    foreach ($tpl['permissions'] as $name) {
                        if (isset($permMap[$name])) {
                            $targetIds[] = $permMap[$name];
                        }
                    }
                }

                $currentIds = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->pluck('permission_id')
                    ->all();

                $toAdd = array_diff($targetIds, $currentIds);
                $toRemove = array_diff($currentIds, $targetIds);

                if ($toAdd) {
                    $rows = array_map(
                        fn ($pid) => ['role_id' => $roleId, 'permission_id' => $pid],
                        $toAdd
                    );
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table('role_has_permissions')->insert($chunk);
                    }
                    $linksInserted += count($toAdd);
                }
                if ($toRemove) {
                    DB::table('role_has_permissions')
                        ->where('role_id', $roleId)
                        ->whereIn('permission_id', $toRemove)
                        ->delete();
                    $linksRemoved += count($toRemove);
                }
            }
        }

        $this->command->info("✓ Roles upserted: {$rolesUpserted} (across {$stores->count()} store(s))");
        $this->command->info("✓ role_has_permissions: +{$linksInserted} added, -{$linksRemoved} removed");
    }
}
