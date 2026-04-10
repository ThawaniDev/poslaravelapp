<?php

namespace Tests\Feature\Workflow;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Base class for all Workflow tests.
 *
 * The CheckPermission middleware uses a custom multi-tenant approach:
 *   - Roles have a `store_id` column and `guard_name` = 'staff'
 *   - Permissions have `guard_name` = 'staff'
 *   - Owner role bypasses all checks via DB query on model_has_roles + roles.store_id
 *   - Non-owner roles checked via RoleService::getEffectivePermissions()
 */
abstract class WorkflowTestCase extends TestCase
{
    protected const ALL_PERMISSIONS = [
        'accessibility.manage','accounting.connect','accounting.export','accounting.manage_mappings',
        'accounting.view_history','auto_update.manage','auto_update.view','backup.manage','backup.view',
        'bakery.custom_orders','bakery.production','bakery.recipes','bakery.view','branches.manage',
        'branches.view','cash.manage','cash.reconciliation','cash.view_daily_summary','cash.view_sessions',
        'companion.view','customers.manage','customers.manage_credit','customers.manage_debits',
        'customers.manage_loyalty','customers.view','dashboard.view','delivery.manage',
        'delivery.manage_config','delivery.sync_menu','delivery.view_dashboard','delivery.view_logs',
        'finance.commissions','finance.expenses','finance.gift_cards','flowers.arrangements',
        'flowers.freshness','flowers.subscriptions','flowers.view','hardware.manage','hardware.view',
        'inventory.adjust','inventory.manage','inventory.purchase_orders','inventory.receive',
        'inventory.recipes','inventory.stocktake','inventory.supplier_returns','inventory.transfer',
        'inventory.view','inventory.write_off','jewelry.buyback','jewelry.manage_details',
        'jewelry.manage_rates','jewelry.view','labels.manage','labels.print','labels.view',
        'layout_builder.manage','layout_builder.view','marketplace.purchase','marketplace.view',
        'mobile.imei','mobile.repairs','mobile.trade_in','mobile.view','nice_to_have.manage',
        'nice_to_have.view','notifications.manage','notifications.schedules','notifications.view',
        'onboarding.manage','orders.manage','orders.return','orders.update_status','orders.view',
        'orders.void','payments.process','pharmacy.drug_schedules','pharmacy.prescriptions',
        'pharmacy.view','pos_customization.manage','pos_customization.view','pos.hold_recall',
        'pos.manage_terminals','pos.return','pos.sell','pos.shift_close','pos.shift_open',
        'pos.view_sessions','pos.void_transaction','products.manage','products.manage_categories',
        'products.manage_pricing','products.manage_suppliers','products.use_predefined','products.view',
        'promotions.apply_manual','promotions.manage','promotions.view_analytics','reports.attendance',
        'reports.customers','reports.export','reports.inventory','reports.sales','reports.staff',
        'reports.view','reports.view_financial','reports.view_margin','restaurant.kds',
        'restaurant.reservations','restaurant.tables','restaurant.tabs','restaurant.view',
        'roles.assign','roles.create','roles.delete','roles.edit','roles.view',
        'security.manage_policies','security.view_audit','security.view_dashboard',
        'settings.localization','settings.manage','settings.view','staff.create','staff.delete',
        'staff.edit','staff.manage','staff.manage_pin','staff.manage_shifts','staff.view',
        'subscription.manage','subscription.view','support.create_ticket','support.view',
        'sync.manage','sync.view','thawani.manage_config','thawani.menu','thawani.view_dashboard',
        'zatca.manage','zatca.view',
        'transactions.view','transactions.export','transactions.void',
    ];

    /**
     * Seed all permissions with guard_name = 'staff'.
     */
    protected function seedPermissions(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ALL_PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'staff']);
        }
    }

    /**
     * Create a store-scoped role and assign a user to it.
     *
     * @param  mixed   $user      User model instance
     * @param  string  $roleName  Role name (e.g. 'owner', 'cashier', 'branch_manager')
     * @param  string  $storeId   The store UUID
     * @param  array   $permissions  Permissions to attach (empty = all for owner)
     */
    protected function assignStoreRole($user, string $roleName, string $storeId, array $permissions = []): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Use unique name per store to avoid Spatie's global unique constraint on (name, guard_name)
        // Exception: 'owner' must be exact — CheckPermission middleware checks roles.name = 'owner'
        $uniqueRoleName = ($roleName === 'owner') ? 'owner' : ($roleName . '_' . substr($storeId, 0, 8));

        $role = Role::where('name', $uniqueRoleName)
            ->where('guard_name', 'staff')
            ->first();

        if (!$role) {
            $role = Role::create([
                'store_id'      => $storeId,
                'name'          => $uniqueRoleName,
                'display_name'  => ucfirst(str_replace('_', ' ', $roleName)),
                'guard_name'    => 'staff',
                'is_predefined' => true,
                'scope'         => 'branch',
            ]);
        }

        $allPerms = Permission::where('guard_name', 'staff')->get();

        if ($roleName === 'owner' || empty($permissions)) {
            $role->syncPermissions($allPerms);
        } else {
            $permModels = $allPerms->whereIn('name', $permissions);
            $role->syncPermissions($permModels);
        }

        // Directly insert into model_has_roles (compatible with custom CheckPermission middleware)
        DB::table('model_has_roles')->updateOrInsert([
            'role_id'    => $role->id,
            'model_type' => get_class($user),
            'model_id'   => $user->id,
        ]);
    }

    /**
     * Convenience: assign owner role to user for a specific store.
     */
    protected function assignOwnerRole($user, string $storeId): void
    {
        $this->assignStoreRole($user, 'owner', $storeId);
    }

    /**
     * Convenience: assign cashier role with operational permissions.
     */
    protected function assignCashierRole($user, string $storeId): void
    {
        $this->assignStoreRole($user, 'cashier', $storeId, [
            'pos.sell', 'pos.return', 'pos.hold_recall', 'pos.shift_open', 'pos.shift_close',
            'pos.view_sessions', 'orders.manage', 'orders.view', 'orders.update_status',
            'orders.void', 'orders.return',
            'customers.view', 'customers.manage', 'products.view', 'inventory.view',
            'cash.manage', 'cash.view_sessions', 'reports.view', 'dashboard.view',
            'restaurant.view', 'restaurant.kds', 'restaurant.tables',
            'zatca.view', 'transactions.view',
        ]);
    }

    /**
     * Convenience: assign branch_manager role with expanded permissions.
     */
    protected function assignBranchManagerRole($user, string $storeId): void
    {
        $this->assignStoreRole($user, 'branch_manager', $storeId, [
            'pos.sell', 'pos.return', 'pos.hold_recall', 'pos.shift_open', 'pos.shift_close',
            'pos.view_sessions', 'pos.void_transaction', 'pos.manage_terminals',
            'orders.manage', 'orders.view', 'orders.update_status', 'orders.return', 'orders.void',
            'customers.view', 'customers.manage', 'customers.manage_loyalty',
            'customers.manage_credit', 'customers.manage_debits',
            'products.view', 'products.manage',
            'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
            'inventory.stocktake', 'inventory.purchase_orders', 'inventory.receive', 'inventory.write_off',
            'staff.view', 'staff.create', 'staff.edit', 'staff.manage_shifts', 'staff.manage_pin',
            'reports.view', 'reports.sales', 'reports.inventory', 'reports.staff', 'reports.attendance',
            'reports.customers', 'reports.export', 'reports.view_financial',
            'finance.expenses', 'finance.commissions', 'finance.gift_cards',
            'cash.manage', 'cash.view_sessions', 'cash.reconciliation', 'cash.view_daily_summary',
            'delivery.view_dashboard', 'delivery.manage', 'delivery.view_logs',
            'branches.view', 'roles.view', 'dashboard.view',
            'restaurant.view', 'restaurant.kds', 'restaurant.tables',
            'zatca.view', 'transactions.view', 'transactions.export', 'transactions.void',
        ]);
    }
}
