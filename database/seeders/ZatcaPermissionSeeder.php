<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent ZATCA permission backfill.
 *
 * Why this exists:
 *  • PermissionService::ALL_PERMISSIONS and RoleService::DEFAULT_ROLE_TEMPLATES
 *    already include `zatca.view` / `zatca.manage`. AllPermissionsSyncSeeder syncs
 *    the Permission rows; RoleService::seedPredefinedRoles attaches them to roles
 *    when a store is created.
 *  • However, `seedPredefinedRoles` runs ONLY ONCE per store at creation time. Stores
 *    that existed before `zatca.view` / `zatca.manage` were added do NOT have those
 *    permissions attached to their per-store roles (chain_manager, branch_manager,
 *    accountant, cashier). Owner is unaffected — RoleService::getEffectivePermissions
 *    short-circuits to all permissions for UserRole::Owner.
 *  • This seeder backfills the missing role↔permission attachments and removes any
 *    legacy underscore-named ZATCA permissions seeded under the wrong guard.
 *
 * Safe to re-run.
 *
 *   php artisan db:seed --class=ZatcaPermissionSeeder --force
 */
class ZatcaPermissionSeeder extends Seeder
{
    private const CANONICAL = [
        'zatca.view'   => 'View ZATCA compliance dashboard',
        'zatca.manage' => 'Submit invoices and manage enrollment',
    ];

    private const LEGACY = [
        'zatca_invoice_view',
        'zatca_invoice_submit',
        'zatca_certificate_view',
        'zatca_certificate_enroll',
    ];

    private const ROLE_GRANTS = [
        'chain_manager'  => ['zatca.view', 'zatca.manage'],
        'branch_manager' => ['zatca.view', 'zatca.manage'],
        'accountant'     => ['zatca.view'],
        'cashier'        => ['zatca.view'],
    ];

    public function run(): void
    {
        $this->cleanupLegacy();
        $permIds = $this->ensureCanonical();
        $this->backfillRoles($permIds);
    }

    private function cleanupLegacy(): void
    {
        $legacyIds = DB::table('permissions')
            ->whereIn('name', self::LEGACY)
            ->pluck('id');

        if ($legacyIds->isEmpty()) {
            $this->command?->info('  ✓ No legacy ZATCA permissions found.');
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $legacyIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $legacyIds)->delete();
        DB::table('permissions')->whereIn('id', $legacyIds)->delete();

        $this->command?->info('  ✓ Removed ' . $legacyIds->count() . ' legacy ZATCA permission row(s).');
    }

    /**
     * @return array<string,int>
     */
    private function ensureCanonical(): array
    {
        $ids = [];
        foreach (self::CANONICAL as $name => $description) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'staff'],
                [
                    'module'      => 'zatca',
                    'description' => $description,
                ]
            );
            $ids[$name] = $perm->id;
        }
        $this->command?->info('  ✓ Canonical ZATCA permissions present (staff guard).');
        return $ids;
    }

    /**
     * @param array<string,int> $permIds
     */
    private function backfillRoles(array $permIds): void
    {
        $totalAttached = 0;

        foreach (self::ROLE_GRANTS as $roleName => $perms) {
            $roleIds = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'staff')
                ->pluck('id');

            if ($roleIds->isEmpty()) {
                continue;
            }

            foreach ($perms as $permName) {
                $permId = $permIds[$permName] ?? null;
                if (! $permId) {
                    continue;
                }

                $existingRoleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $permId)
                    ->whereIn('role_id', $roleIds)
                    ->pluck('role_id')
                    ->all();
                $existingSet = array_flip($existingRoleIds);

                $rows = [];
                foreach ($roleIds as $rid) {
                    if (isset($existingSet[$rid])) {
                        continue;
                    }
                    $rows[] = [
                        'permission_id' => $permId,
                        'role_id'       => $rid,
                    ];
                }

                if ($rows) {
                    DB::table('role_has_permissions')->insert($rows);
                    $totalAttached += count($rows);
                }
            }
        }

        $this->command?->info("  ✓ Backfilled {$totalAttached} role↔permission link(s) across stores.");

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
