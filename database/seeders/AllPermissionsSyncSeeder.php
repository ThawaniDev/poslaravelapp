<?php

namespace Database\Seeders;

use App\Domain\StaffManagement\Services\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single source-of-truth permission sync.
 *
 *  • permissions               ← PermissionService::ALL_PERMISSIONS (187 provider perms, Spatie runtime)
 *  • provider_permissions      ← mirrored from same canonical list
 *  • admin_permissions         ← appends 19 platform-admin perms missing from permissions_seed.sql
 *  • admin_role_permissions    ← grants new admin perms to super_admin
 *
 * Idempotent — safe to re-run.
 *
 * Run: php artisan db:seed --class=AllPermissionsSyncSeeder --force
 */
class AllPermissionsSyncSeeder extends Seeder
{
    /**
     * Platform-admin perms referenced by Filament resources/pages but absent
     * from permissions_seed.sql. Source: codebase audit.
     */
    private const ADMIN_EXTRA_PERMISSIONS = [
        // ── Analytics dashboards (Filament) ─────────────────────
        ['name' => 'analytics.features',        'group' => 'analytics', 'description' => 'View feature adoption analytics dashboard'],
        ['name' => 'analytics.notifications',   'group' => 'analytics', 'description' => 'View notification analytics dashboard'],
        ['name' => 'analytics.revenue',         'group' => 'analytics', 'description' => 'View revenue dashboard'],
        ['name' => 'analytics.stores',          'group' => 'analytics', 'description' => 'View store performance dashboard'],
        ['name' => 'analytics.subscriptions',   'group' => 'analytics', 'description' => 'View subscription analytics dashboard'],
        ['name' => 'analytics.support',         'group' => 'analytics', 'description' => 'View support analytics dashboard'],

        // ── Provider payments (PayTabs) ─────────────────────────
        ['name' => 'provider_payments.view',    'group' => 'provider_payments', 'description' => 'View provider payment list and details'],
        ['name' => 'provider_payments.manage',  'group' => 'provider_payments', 'description' => 'Manage provider payments, resend, query gateway'],
        ['name' => 'provider_payments.refund',  'group' => 'provider_payments', 'description' => 'Refund provider payments'],

        // ── Subscription / billing extras ───────────────────────
        ['name' => 'subscription.view',         'group' => 'billing',   'description' => 'View store subscriptions'],
        ['name' => 'subscription.manage',       'group' => 'billing',   'description' => 'Manage store subscription plans and billing'],
        ['name' => 'subscriptions.view',        'group' => 'billing',   'description' => 'Alias of subscription.view (used by PaymentReminderResource)'],

        // ── POS terminals (RegisterResource) ────────────────────
        ['name' => 'terminals.view',            'group' => 'terminals', 'description' => 'View POS terminals across stores'],
        ['name' => 'terminals.create',          'group' => 'terminals', 'description' => 'Register new POS terminals'],
        ['name' => 'terminals.edit',            'group' => 'terminals', 'description' => 'Edit POS terminal configuration'],
        ['name' => 'terminals.delete',          'group' => 'terminals', 'description' => 'Decommission POS terminals'],

        // ── POS sessions (PosSessionResource) ───────────────────
        ['name' => 'pos_sessions.view',         'group' => 'pos',       'description' => 'View POS shifts (sessions) across stores'],
        ['name' => 'pos_sessions.manage',       'group' => 'pos',       'description' => 'Force-close POS shifts and reopen for corrections'],

        // ── Transactions (TransactionResource) ──────────────────
        ['name' => 'transactions.view',         'group' => 'pos',       'description' => 'View POS transactions across stores'],
        ['name' => 'transactions.export',       'group' => 'pos',       'description' => 'Export POS transactions to CSV/PDF'],
        ['name' => 'transactions.void',         'group' => 'pos',       'description' => 'Void completed POS transactions from admin'],

        // ── Held carts (HeldCartResource) ───────────────────────
        ['name' => 'held_carts.view',           'group' => 'pos',       'description' => 'View held carts across registers'],
        ['name' => 'held_carts.manage',         'group' => 'pos',       'description' => 'Delete held carts (cleanup stale carts)'],

        // ── Thawani admin pages ─────────────────────────────────
        ['name' => 'thawani.manage_config',     'group' => 'integrations', 'description' => 'Manage Thawani product/column mappings and store connection'],
        ['name' => 'thawani.view_sync_logs',    'group' => 'integrations', 'description' => 'View Thawani sync logs'],

        // ── Installments admin ──────────────────────────────────
        ['name' => 'installments.configure',    'group' => 'integrations', 'description' => 'Configure installment provider credentials'],
    ];

    public function run(): void
    {
        $this->syncProviderPermissions();
        $this->syncAdminPermissions();
    }

    /**
     * Mirror PermissionService::ALL_PERMISSIONS into both `permissions`
     * (Spatie runtime) and `provider_permissions` (admin registry).
     */
    private function syncProviderPermissions(): void
    {
        $this->command->info('Syncing PermissionService::ALL_PERMISSIONS → permissions + provider_permissions...');

        // Flatten the canonical list once.
        $all = [];
        $sortOrder = 0;
        foreach (PermissionService::ALL_PERMISSIONS as $module => $perms) {
            foreach ($perms as $perm) {
                $sortOrder++;
                $all[] = [
                    'name'            => $perm['name'],
                    'module'          => $module,
                    'display_name'    => $perm['display_name'],
                    'display_name_ar' => $perm['display_name_ar'] ?? null,
                    'description'     => $perm['description'] ?? null,
                    'description_ar'  => $perm['description_ar'] ?? null,
                    'requires_pin'    => $perm['requires_pin'] ?? false,
                    'sort_order'      => $sortOrder,
                ];
            }
        }

        // 1) permissions (Spatie runtime, guard='staff') — bulk-insert missing only.
        $existingPermNames = DB::table('permissions')
            ->where('guard_name', 'staff')
            ->pluck('name')
            ->all();
        $existingSet = array_flip($existingPermNames);
        $now = now();
        $rowsToInsert = [];
        foreach ($all as $p) {
            if (isset($existingSet[$p['name']])) {
                continue;
            }
            $rowsToInsert[] = [
                'name'            => $p['name'],
                'guard_name'      => 'staff',
                'module'          => $p['module'],
                'display_name'    => $p['display_name'],
                'display_name_ar' => $p['display_name_ar'],
                'description'     => $p['description'],
                'description_ar'  => $p['description_ar'],
                'requires_pin'    => $p['requires_pin'],
                'sort_order'      => $p['sort_order'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }
        if ($rowsToInsert) {
            foreach (array_chunk($rowsToInsert, 100) as $chunk) {
                DB::table('permissions')->insert($chunk);
            }
        }
        $this->command->info('  ✓ permissions (staff guard): +' . count($rowsToInsert) . ' inserted, ' . count($existingPermNames) . ' already present.');

        // 2) provider_permissions — bulk-insert missing only.
        $existingProvNames = DB::table('provider_permissions')->pluck('name')->all();
        $existingProvSet = array_flip($existingProvNames);
        $rowsToInsert = [];
        foreach ($all as $p) {
            if (isset($existingProvSet[$p['name']])) {
                continue;
            }
            $rowsToInsert[] = [
                'id'              => (string) Str::uuid(),
                'name'            => $p['name'],
                'group'           => $p['module'],
                'description'     => $p['description'] ?? $p['display_name'],
                'description_ar'  => $p['description_ar'] ?? $p['display_name_ar'],
                'is_active'       => true,
                'created_at'      => $now,
            ];
        }
        if ($rowsToInsert) {
            foreach (array_chunk($rowsToInsert, 100) as $chunk) {
                DB::table('provider_permissions')->insert($chunk);
            }
        }
        $this->command->info('  ✓ provider_permissions: +' . count($rowsToInsert) . ' inserted, ' . count($existingProvNames) . ' already present.');
    }

    /**
     * Append 19 admin perms missing from permissions_seed.sql and grant them to super_admin.
     */
    private function syncAdminPermissions(): void
    {
        $this->command->info('Adding missing admin_permissions...');

        $superAdminId = DB::table('admin_roles')->where('slug', 'super_admin')->value('id');
        if (!$superAdminId) {
            $this->command->warn('  super_admin role not found — skipping admin perm grants.');
        }

        $created = 0;
        $granted = 0;
        foreach (self::ADMIN_EXTRA_PERMISSIONS as $perm) {
            $existing = DB::table('admin_permissions')->where('name', $perm['name'])->first();
            if (!$existing) {
                $id = (string) Str::uuid();
                DB::table('admin_permissions')->insert([
                    'id'          => $id,
                    'name'        => $perm['name'],
                    'group'       => $perm['group'],
                    'description' => $perm['description'],
                    'created_at'  => now(),
                ]);
                $created++;
            } else {
                $id = $existing->id;
            }

            if ($superAdminId) {
                $linked = DB::table('admin_role_permissions')
                    ->where('admin_role_id', $superAdminId)
                    ->where('admin_permission_id', $id)
                    ->exists();
                if (!$linked) {
                    DB::table('admin_role_permissions')->insert([
                        'admin_role_id'       => $superAdminId,
                        'admin_permission_id' => $id,
                    ]);
                    $granted++;
                }
            }
        }
        $this->command->info("  ✓ admin_permissions: +{$created} created.");
        $this->command->info("  ✓ admin_role_permissions: +{$granted} granted to super_admin.");
    }
}
