<?php

namespace App\Domain\StaffManagement\Services;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Security\Enums\RoleAuditAction;
use App\Domain\Security\Models\RoleAuditLog;
use App\Domain\StaffManagement\Models\DefaultRoleTemplate;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * List all roles for a store, auto-syncing any missing default templates first.
     */
    public function listForStore(string $storeId): \Illuminate\Database\Eloquent\Collection
    {
        $this->syncDefaultTemplates($storeId);

        return Role::forStore($storeId)
            ->with('permissions')
            ->orderBy('is_predefined', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Ensure all DefaultRoleTemplates exist as predefined roles for the store.
     * Only creates missing ones — never overwrites existing roles.
     */
    public function syncDefaultTemplates(string $storeId): void
    {
        $templates = DefaultRoleTemplate::with('permissions')->get();

        if ($templates->isEmpty()) {
            return;
        }

        $existingSlugs = Role::forStore($storeId)
            ->where('is_predefined', true)
            ->pluck('name')
            ->all();

        foreach ($templates as $template) {
            if (in_array($template->slug, $existingSlugs, true)) {
                continue;
            }

            $role = Role::create([
                'store_id'      => $storeId,
                'name'          => $template->slug,
                'display_name'  => $template->name,
                'guard_name'    => 'staff',
                'is_predefined' => true,
                'description'   => $template->description,
            ]);

            // Map ProviderPermission names → local Permission records (matched by name)
            if ($template->permissions->isNotEmpty()) {
                $permIds = Permission::whereIn('name', $template->permissions->pluck('name'))->pluck('id');
                $role->permissions()->attach($permIds);
            }
        }
    }

    /**
     * Get a single role with its permissions.
     */
    public function find(int $roleId): Role
    {
        return Role::with('permissions')->findOrFail($roleId);
    }

    /**
     * Create a custom role for a store.
     */
    public function create(array $data, User $actor): Role
    {
        return DB::transaction(function () use ($data, $actor) {
            $role = Role::create([
                'store_id'      => $data['store_id'],
                'name'          => $data['name'],
                'display_name'  => $data['display_name'],
                'guard_name'    => 'staff',
                'is_predefined' => false,
                'description'   => $data['description'] ?? null,
            ]);

            if (!empty($data['permission_ids'])) {
                $role->permissions()->attach($data['permission_ids']);
            }

            $this->audit($role, $actor, RoleAuditAction::RoleCreated, [
                'permissions' => $data['permission_ids'] ?? [],
            ]);

            return $role->load('permissions');
        });
    }

    /**
     * Update a custom role (predefined roles cannot be edited).
     */
    public function update(Role $role, array $data, User $actor): Role
    {
        if ($role->isPredefined()) {
            throw new \InvalidArgumentException('Predefined roles cannot be modified.');
        }

        return DB::transaction(function () use ($role, $data, $actor) {
            $role->update(array_filter([
                'name'         => $data['name'] ?? null,
                'display_name' => $data['display_name'] ?? null,
                'description'  => $data['description'] ?? null,
            ]));

            if (array_key_exists('permission_ids', $data)) {
                $oldPerms = $role->permissions()->pluck('permissions.id')->toArray();
                $role->permissions()->sync($data['permission_ids']);

                $this->audit($role, $actor, RoleAuditAction::RoleUpdated, [
                    'old_permissions' => $oldPerms,
                    'new_permissions' => $data['permission_ids'],
                ]);
            } else {
                $this->audit($role, $actor, RoleAuditAction::RoleUpdated, [
                    'name' => $data['name'] ?? $role->name,
                ]);
            }

            return $role->load('permissions');
        });
    }

    /**
     * Delete a custom role (predefined cannot be deleted).
     */
    public function delete(Role $role, User $actor): void
    {
        if ($role->isPredefined()) {
            throw new \InvalidArgumentException('Predefined roles cannot be deleted.');
        }

        DB::transaction(function () use ($role, $actor) {
            $this->audit($role, $actor, RoleAuditAction::RoleUpdated, [
                'action' => 'deleted',
                'name'   => $role->name,
            ]);

            $role->permissions()->detach();
            $role->delete();
        });
    }

    /**
     * Assign a role to a user (via model_has_roles).
     */
    public function assignToUser(Role $role, User $user, User $actor): void
    {
        DB::table('model_has_roles')->updateOrInsert(
            [
                'role_id'    => $role->id,
                'model_id'   => $user->id,
                'model_type' => get_class($user),
            ],
        );

        $this->audit($role, $actor, RoleAuditAction::PermissionGranted, [
            'assigned_to' => $user->id,
        ]);
    }

    /**
     * Remove a role from a user.
     */
    public function removeFromUser(Role $role, User $user, User $actor): void
    {
        DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->delete();

        $this->audit($role, $actor, RoleAuditAction::PermissionRevoked, [
            'removed_from' => $user->id,
        ]);
    }

    /**
     * Get the effective permissions for a user in a store.
     * Combines all permissions from all assigned roles.
     */
    public function getEffectivePermissions(User $user, string $storeId): array
    {
        // Owner role gets all permissions
        if ($user->role === UserRole::Owner) {
            return Permission::pluck('name')->unique()->values()->toArray();
        }

        $roleIds = DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->pluck('role_id');

        return Permission::whereHas('roles', function ($q) use ($roleIds, $storeId) {
            $q->whereIn('roles.id', $roleIds)
              ->where('roles.store_id', $storeId);
        })->pluck('name')->unique()->values()->toArray();
    }

    /**
     * Check if a user has a specific permission in a store.
     */
    public function userHasPermission(User $user, string $storeId, string $permissionName): bool
    {
        return in_array($permissionName, $this->getEffectivePermissions($user, $storeId));
    }

    /**
     * Seed predefined roles for a newly created store.
     */
    public function seedPredefinedRoles(string $storeId): void
    {
        $templates = config('pos.role_templates', self::DEFAULT_ROLE_TEMPLATES);
        $allPermissions = Permission::all();

        foreach ($templates as $template) {
            $role = Role::create([
                'store_id'        => $storeId,
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
            } else {
                $permIds = $allPermissions->whereIn('name', $template['permissions'])->pluck('id');
                $role->permissions()->attach($permIds);
            }
        }
    }

    /**
     * Get the branch scope for a user in a store.
     * Returns 'organization' if user has any org-level role, 'branch' otherwise.
     */
    public function getUserBranchScope(User $user, string $storeId): string
    {
        // Owner always has organization scope
        if ($user->role === UserRole::Owner) {
            return 'organization';
        }

        $hasOrgRole = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', get_class($user))
            ->where('roles.store_id', $storeId)
            ->where('roles.scope', 'organization')
            ->exists();

        return $hasOrgRole ? 'organization' : 'branch';
    }

    /**
     * Get list of store IDs the user can access in the organization.
     * Org-scoped users can access all stores in their organization.
     * Branch-scoped users can only access their own store.
     */
    public function getAccessibleStoreIds(User $user, string $storeId): array
    {
        $scope = $this->getUserBranchScope($user, $storeId);

        if ($scope === 'organization' && $user->organization_id) {
            return \App\Domain\Core\Models\Store::where('organization_id', $user->organization_id)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
        }

        return [$storeId];
    }

    /**
     * Get per-branch role information for a user across all their accessible stores.
     * Returns array keyed by store_id with role name, display name, and scope.
     */
    public function getUserBranchRoles(User $user, string $storeId): array
    {
        $accessibleStoreIds = $this->getAccessibleStoreIds($user, $storeId);

        $assignments = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', get_class($user))
            ->whereIn('roles.store_id', $accessibleStoreIds)
            ->select('roles.store_id', 'roles.name', 'roles.display_name', 'roles.display_name_ar', 'roles.scope')
            ->get();

        $result = [];
        foreach ($assignments as $row) {
            $result[$row->store_id] = [
                'role_name'       => $row->name,
                'display_name'    => $row->display_name,
                'display_name_ar' => $row->display_name_ar,
                'scope'           => $row->scope,
            ];
        }

        return $result;
    }

    // ─── Private ─────────────────────────────────────────────────

    private function audit(Role $role, User $actor, RoleAuditAction $action, array $details = []): void
    {
        RoleAuditLog::create([
            'store_id'   => $role->store_id,
            'user_id'    => $actor->id,
            'action'     => $action,
            'role_id'    => $role->id,
            'details'    => $details,
            'created_at' => now(),
        ]);
    }

    /**
     * Default role templates — used when no config override exists.
     * Permission names must match PermissionService::ALL_PERMISSIONS exactly.
     *
     * scope: 'organization' = cross-branch access, 'branch' = single branch only
     *
     * 16 predefined roles:
     * Organization-level (7): owner, manager, accountant, chain_manager, inventory_manager, viewer, sales_manager
     * Branch-level (9): branch_manager, branch_accountant, branch_chain_manager, branch_inventory_manager,
     *                    branch_kitchen_staff, senior_cashier, cashier, branch_viewer, branch_sales_manager
     */
    public const DEFAULT_ROLE_TEMPLATES = [

        // ═══════════════════════════════════════════════════════════
        // ORGANIZATION-LEVEL ROLES (cross-branch access)
        // ═══════════════════════════════════════════════════════════

        [
            'name'            => 'owner',
            'display_name'    => 'Owner',
            'display_name_ar' => 'المالك',
            'scope'           => 'organization',
            'description'     => 'Full access to all features, settings, and all branches',
            'description_ar'  => 'وصول كامل لجميع الميزات والإعدادات وجميع الفروع',
            'permissions'     => ['*'], // handled specially — gets ALL permissions
        ],

        [
            'name'            => 'manager',
            'display_name'    => 'Manager',
            'display_name_ar' => 'المدير العام',
            'scope'           => 'organization',
            'description'     => 'Manage all store operations, staff, and reports across branches',
            'description_ar'  => 'إدارة جميع عمليات المتجر والموظفين والتقارير عبر الفروع',
            'permissions'     => [
                // POS
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.discount', 'pos.approve_discount',
                'pos.void', 'pos.void_transaction', 'pos.edit_transaction', 'pos.refund', 'pos.return', 'pos.tax_exempt',
                'pos.hold_recall', 'pos.reprint_receipt', 'pos.price_override', 'pos.no_sale',
                'pos.view_sessions', 'pos.manage_terminals',
                // Orders
                'orders.view', 'orders.manage', 'orders.return', 'orders.void', 'orders.update_status',
                // Transactions
                'transactions.view', 'transactions.export', 'transactions.void',
                // Products
                'products.view', 'products.manage', 'products.manage_categories', 'products.manage_suppliers',
                'products.import_export', 'products.manage_pricing', 'products.use_predefined',
                // Inventory
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
                'inventory.stocktake', 'inventory.receive', 'inventory.purchase_orders',
                'inventory.supplier_returns', 'inventory.recipes', 'inventory.write_off',
                // Customers
                'customers.view', 'customers.manage', 'customers.manage_loyalty',
                'customers.manage_credit', 'customers.manage_debits',
                // Payments & Cash
                'payments.process', 'payments.refund',
                'cash.manage', 'cash.view_sessions', 'cash.view_daily_summary', 'cash.reconciliation',
                // Installments
                'installments.configure', 'installments.use', 'installments.view_history',
                // Finance
                // Reports
                'reports.view', 'reports.view_financial', 'reports.view_margin', 'reports.attendance',
                'reports.sales', 'reports.inventory', 'reports.customers', 'reports.staff', 'reports.export',
                // Staff
                'staff.view', 'staff.create', 'staff.edit', 'staff.delete', 'staff.manage',
                'staff.manage_pin', 'staff.manage_shifts', 'staff.training_mode',
                // Roles
                'roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'roles.audit', 'roles.assign',
                // Labels & Promotions
                'labels.view', 'labels.manage', 'labels.print',
                'promotions.manage', 'promotions.apply_manual', 'promotions.view_analytics',
                // Settings
                'settings.view', 'settings.manage', 'settings.hardware', 'settings.sync',
                'settings.thawani', 'settings.updates', 'settings.backup', 'settings.tax',
                'settings.receipt', 'settings.store_profile', 'settings.localization', 'settings.pos_behavior',
                // Integrations
                'accounting.configure', 'accounting.export', 'accounting.view_history', 'accounting.manage_mappings',
                'thawani.menu', 'thawani.view_dashboard', 'thawani.manage_config',
                'delivery.manage', 'delivery.view_dashboard', 'delivery.manage_config', 'delivery.sync_menu', 'delivery.view_logs',
                // Notifications & Support
                'notifications.view', 'notifications.manage', 'notifications.schedules',
                'support.view', 'support.create_ticket',
                // Branches
                'branches.view', 'branches.manage',
                // Security
                'security.view_dashboard', 'security.view_audit',
                // Dashboard & Companion
                'dashboard.view', 'companion.view',
                // System
                'sync.view', 'sync.manage',
                'hardware.view', 'hardware.manage',
                'backup.view', 'backup.manage',
                'pos_customization.view', 'pos_customization.manage',
                'layout_builder.view', 'layout_builder.manage',
                'marketplace.view', 'marketplace.purchase',
                'accessibility.manage',
                'nice_to_have.view', 'nice_to_have.manage',
                'onboarding.manage',
                'auto_update.view', 'auto_update.manage',
                // ZATCA
                'zatca.view', 'zatca.manage',
                // Industry modules
                'bakery.view', 'bakery.recipes', 'bakery.custom_orders', 'bakery.production',
                'mobile.repairs', 'mobile.trade_in', 'mobile.imei', 'mobile.view',
                'flowers.arrangements', 'flowers.subscriptions', 'flowers.freshness', 'flowers.view',
                'jewelry.manage_rates', 'jewelry.buyback', 'jewelry.view', 'jewelry.manage_details',
                'pharmacy.prescriptions', 'pharmacy.controlled_substances', 'pharmacy.view', 'pharmacy.drug_schedules',
                'restaurant.tables', 'restaurant.kds', 'restaurant.reservations', 'restaurant.tabs', 'restaurant.split_bill', 'restaurant.view',
                // Wameed AI
                'wameed_ai.view', 'wameed_ai.use', 'wameed_ai.manage',
                // Cashier Gamification
                'cashier_performance.view_leaderboard', 'cashier_performance.view_badges', 'cashier_performance.manage_badges',
                'cashier_performance.view_anomalies', 'cashier_performance.view_reports', 'cashier_performance.manage_settings',
            ],
        ],

        [
            'name'            => 'accountant',
            'display_name'    => 'Accountant',
            'display_name_ar' => 'محاسب',
            'scope'           => 'organization',
            'description'     => 'Financial reporting, accounting, and cash reconciliation across all branches',
            'description_ar'  => 'التقارير المالية والمحاسبة والمطابقة المالية عبر جميع الفروع',
            'permissions'     => [
                // Reports (full financial)
                'reports.view', 'reports.view_financial', 'reports.view_margin', 'reports.sales',
                'reports.inventory', 'reports.customers', 'reports.staff', 'reports.attendance', 'reports.export',
                // Accounting
                'accounting.configure', 'accounting.connect', 'accounting.export', 'accounting.view_history', 'accounting.manage_mappings',
                // Finance
                'finance.commissions', 'finance.settlements', 'finance.expenses', 'finance.gift_cards',
                // Cash
                'cash.view_sessions', 'cash.view_daily_summary', 'cash.reconciliation',
                // Installments
                'installments.view_history',
                // Orders & POS (view only)
                'orders.view', 'pos.view_sessions',
                // Transactions
                'transactions.view', 'transactions.export',
                // Customers (debits)
                'customers.view', 'customers.manage_debits',
                // Products (view for costing)
                'products.view',
                // Inventory (view for valuation)
                'inventory.view',
                // ZATCA
                'zatca.view', 'zatca.manage',
                // Branches, Dashboard
                'branches.view', 'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],

        [
            'name'            => 'chain_manager',
            'display_name'    => 'Chain Manager',
            'display_name_ar' => 'مدير السلسلة',
            'scope'           => 'organization',
            'description'     => 'Multi-branch operations, inventory transfers, and cross-branch reporting',
            'description_ar'  => 'عمليات متعددة الفروع ونقل المخزون والتقارير عبر الفروع',
            'permissions'     => [
                // Branches (full)
                'branches.view', 'branches.manage',
                // Inventory (cross-branch)
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
                'inventory.stocktake', 'inventory.receive', 'inventory.purchase_orders',
                'inventory.supplier_returns', 'inventory.recipes',
                // Products
                'products.view', 'products.manage', 'products.manage_categories', 'products.manage_suppliers',
                'products.import_export', 'products.manage_pricing',
                // Orders
                'orders.view', 'orders.manage', 'orders.update_status',
                // Transactions
                'transactions.view', 'transactions.export',
                // Reports
                'reports.view', 'reports.sales', 'reports.inventory', 'reports.customers', 'reports.export',
                // Staff (view)
                'staff.view',
                // Labels
                'labels.view', 'labels.manage', 'labels.print',
                // Delivery
                'delivery.manage', 'delivery.view_dashboard', 'delivery.manage_config', 'delivery.sync_menu', 'delivery.view_logs',
                // Sync
                'sync.view', 'sync.manage',
                // Dashboard
                'dashboard.view', 'companion.view',
                'notifications.view', 'notifications.manage',
                'support.view', 'support.create_ticket',
            ],
        ],

        [
            'name'            => 'inventory_manager',
            'display_name'    => 'Inventory Manager',
            'display_name_ar' => 'مدير المخزون',
            'scope'           => 'organization',
            'description'     => 'Full inventory management, purchasing, and stock control across all branches',
            'description_ar'  => 'إدارة المخزون الكاملة والمشتريات ومراقبة المخزون عبر جميع الفروع',
            'permissions'     => [
                // Inventory (full)
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
                'inventory.stocktake', 'inventory.receive', 'inventory.purchase_orders',
                'inventory.supplier_returns', 'inventory.recipes', 'inventory.write_off',
                // Products (full)
                'products.view', 'products.manage', 'products.manage_categories', 'products.manage_suppliers',
                'products.import_export', 'products.manage_pricing', 'products.use_predefined',
                // Labels
                'labels.view', 'labels.manage', 'labels.print',
                // Reports (inventory focused)
                'reports.view', 'reports.inventory', 'reports.export',
                // Branches
                'branches.view',
                // Dashboard
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],

        [
            'name'            => 'viewer',
            'display_name'    => 'Viewer',
            'display_name_ar' => 'مشاهد',
            'scope'           => 'organization',
            'description'     => 'Read-only access to dashboards, reports, and data across all branches',
            'description_ar'  => 'صلاحية العرض فقط للوحات المعلومات والتقارير والبيانات عبر جميع الفروع',
            'permissions'     => [
                'dashboard.view', 'companion.view',
                'reports.view', 'reports.view_financial', 'reports.view_margin', 'reports.sales',
                'reports.inventory', 'reports.customers', 'reports.staff', 'reports.attendance',
                'orders.view', 'products.view', 'inventory.view', 'customers.view',
                'transactions.view',
                'pos.view_sessions', 'cash.view_sessions', 'cash.view_daily_summary',
                'branches.view',
                'accounting.view_history',
                'installments.view_history',
                'labels.view', 'promotions.view_analytics',
                'delivery.view_dashboard', 'delivery.view_logs',
                'thawani.view_dashboard',
                'security.view_dashboard', 'security.view_audit',
                'sync.view', 'hardware.view', 'backup.view',
                'zatca.view',
                'notifications.view',
                'support.view',
                // Wameed AI
                'wameed_ai.view',
            ],
        ],

        [
            'name'            => 'sales_manager',
            'display_name'    => 'Sales Manager',
            'display_name_ar' => 'مدير المبيعات',
            'scope'           => 'organization',
            'description'     => 'Sales operations, customer management, and promotions across all branches',
            'description_ar'  => 'عمليات المبيعات وإدارة العملاء والعروض الترويجية عبر جميع الفروع',
            'permissions'     => [
                // POS
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.discount', 'pos.approve_discount',
                'pos.void', 'pos.void_transaction', 'pos.edit_transaction', 'pos.refund', 'pos.return',
                'pos.hold_recall', 'pos.reprint_receipt', 'pos.price_override', 'pos.view_sessions',
                // Orders
                'orders.view', 'orders.manage', 'orders.return', 'orders.void', 'orders.update_status',
                // Transactions
                'transactions.view', 'transactions.export', 'transactions.void',
                // Products (view + pricing)
                'products.view', 'products.manage_pricing',
                // Customers (full)
                'customers.view', 'customers.manage', 'customers.manage_loyalty',
                'customers.manage_credit', 'customers.manage_debits',
                // Payments
                'payments.process', 'payments.refund',
                // Cash
                'cash.manage', 'cash.view_sessions', 'cash.view_daily_summary',
                // Installments
                'installments.use', 'installments.view_history',
                // Finance
                'finance.commissions', 'finance.gift_cards',
                // Promotions
                'promotions.manage', 'promotions.apply_manual', 'promotions.view_analytics',
                // Reports (sales)
                'reports.view', 'reports.sales', 'reports.customers', 'reports.export',
                // Labels
                'labels.view', 'labels.print',
                // Branches, Dashboard
                'branches.view', 'dashboard.view', 'companion.view',
                'notifications.view', 'notifications.manage',
                'support.view', 'support.create_ticket',                // Wameed AI
                'wameed_ai.view', 'wameed_ai.use',            ],
        ],

        // ═══════════════════════════════════════════════════════════
        // BRANCH-LEVEL ROLES (single branch access only)
        // ═══════════════════════════════════════════════════════════

        [
            'name'            => 'branch_manager',
            'display_name'    => 'Branch Manager',
            'display_name_ar' => 'مدير الفرع',
            'scope'           => 'branch',
            'description'     => 'Full management of a single branch — operations, staff, and reports',
            'description_ar'  => 'إدارة كاملة لفرع واحد — العمليات والموظفين والتقارير',
            'permissions'     => [
                // POS
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.discount', 'pos.approve_discount',
                'pos.void', 'pos.void_transaction', 'pos.edit_transaction', 'pos.refund', 'pos.return',
                'pos.hold_recall', 'pos.reprint_receipt', 'pos.price_override', 'pos.no_sale',
                'pos.view_sessions', 'pos.manage_terminals',
                // Orders
                'orders.view', 'orders.manage', 'orders.return', 'orders.void', 'orders.update_status',
                // Transactions
                'transactions.view', 'transactions.export', 'transactions.void',
                // Products
                'products.view', 'products.manage', 'products.manage_categories', 'products.manage_suppliers',
                'products.import_export', 'products.manage_pricing',
                // Inventory
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
                'inventory.stocktake', 'inventory.receive', 'inventory.purchase_orders',
                'inventory.supplier_returns', 'inventory.recipes',
                // Customers
                'customers.view', 'customers.manage', 'customers.manage_loyalty',
                'customers.manage_credit', 'customers.manage_debits',
                // Payments & Cash
                'payments.process', 'payments.refund',
                'cash.manage', 'cash.view_sessions', 'cash.view_daily_summary', 'cash.reconciliation',
                // Installments
                'installments.configure', 'installments.use', 'installments.view_history',
                // Finance
                'finance.commissions', 'finance.expenses', 'finance.gift_cards',
                // Reports
                'reports.view', 'reports.view_financial', 'reports.sales', 'reports.inventory',
                'reports.customers', 'reports.staff', 'reports.attendance', 'reports.export',
                // Staff
                'staff.view', 'staff.create', 'staff.edit', 'staff.manage', 'staff.manage_shifts',
                'roles.view', 'roles.assign',
                // Labels & Promotions
                'labels.view', 'labels.manage', 'labels.print',
                'promotions.manage', 'promotions.apply_manual', 'promotions.view_analytics',
                // Settings (limited)
                'settings.view', 'settings.hardware', 'settings.localization', 'settings.receipt', 'settings.pos_behavior',
                // Delivery
                'delivery.manage', 'delivery.view_dashboard',
                // Dashboard
                'dashboard.view',
                'notifications.view', 'notifications.manage',
                'support.view', 'support.create_ticket',
                // Industry modules
                'bakery.view', 'bakery.recipes', 'bakery.custom_orders', 'bakery.production',
                'mobile.repairs', 'mobile.trade_in', 'mobile.imei', 'mobile.view',
                'flowers.arrangements', 'flowers.subscriptions', 'flowers.freshness', 'flowers.view',
                'jewelry.manage_rates', 'jewelry.buyback', 'jewelry.view', 'jewelry.manage_details',
                'pharmacy.prescriptions', 'pharmacy.controlled_substances', 'pharmacy.view', 'pharmacy.drug_schedules',
                'restaurant.tables', 'restaurant.kds', 'restaurant.reservations', 'restaurant.tabs', 'restaurant.split_bill', 'restaurant.view',
                // Wameed AI
                'wameed_ai.view', 'wameed_ai.use', 'wameed_ai.manage',
                // Cashier Gamification
                'cashier_performance.view_leaderboard', 'cashier_performance.view_badges', 'cashier_performance.manage_badges',
                'cashier_performance.view_anomalies', 'cashier_performance.view_reports', 'cashier_performance.manage_settings',
            ],
        ],

        [
            'name'            => 'branch_accountant',
            'display_name'    => 'Branch Accountant',
            'display_name_ar' => 'محاسب الفرع',
            'scope'           => 'branch',
            'description'     => 'Financial reporting and cash management for a single branch',
            'description_ar'  => 'التقارير المالية وإدارة النقد لفرع واحد',
            'permissions'     => [
                'reports.view', 'reports.view_financial', 'reports.view_margin', 'reports.sales',
                'reports.inventory', 'reports.customers', 'reports.staff', 'reports.attendance', 'reports.export',
                'accounting.configure', 'accounting.export', 'accounting.view_history', 'accounting.manage_mappings',
                'finance.commissions', 'finance.settlements', 'finance.expenses', 'finance.gift_cards',
                'cash.view_sessions', 'cash.view_daily_summary', 'cash.reconciliation',
                // Installments
                'installments.view_history',
                'orders.view', 'pos.view_sessions',
                // Transactions
                'transactions.view', 'transactions.export',
                'customers.view', 'customers.manage_debits',
                'products.view', 'inventory.view',
                'zatca.view',
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],

        [
            'name'            => 'branch_chain_manager',
            'display_name'    => 'Branch Chain Manager',
            'display_name_ar' => 'مدير سلسلة التوريد بالفرع',
            'scope'           => 'branch',
            'description'     => 'Inventory transfers, procurement, and supply chain for a single branch',
            'description_ar'  => 'نقل المخزون والمشتريات وسلسلة التوريد لفرع واحد',
            'permissions'     => [
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
                'inventory.stocktake', 'inventory.receive', 'inventory.purchase_orders',
                'inventory.supplier_returns', 'inventory.recipes',
                'products.view', 'products.manage', 'products.manage_categories', 'products.manage_suppliers',
                'products.import_export',
                'orders.view', 'orders.manage', 'orders.update_status',
                'delivery.manage', 'delivery.view_dashboard', 'delivery.sync_menu', 'delivery.view_logs',
                'labels.view', 'labels.manage', 'labels.print',
                'reports.view', 'reports.inventory', 'reports.export',
                'sync.view',
                'dashboard.view',
                'notifications.view', 'notifications.manage',
                'support.view', 'support.create_ticket',
            ],
        ],

        [
            'name'            => 'branch_inventory_manager',
            'display_name'    => 'Branch Inventory Manager',
            'display_name_ar' => 'مدير مخزون الفرع',
            'scope'           => 'branch',
            'description'     => 'Full inventory management, purchasing, and stock control for a single branch',
            'description_ar'  => 'إدارة المخزون الكاملة والمشتريات ومراقبة المخزون لفرع واحد',
            'permissions'     => [
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
                'inventory.stocktake', 'inventory.receive', 'inventory.purchase_orders',
                'inventory.supplier_returns', 'inventory.recipes', 'inventory.write_off',
                'products.view', 'products.manage', 'products.manage_categories', 'products.manage_suppliers',
                'products.import_export', 'products.manage_pricing', 'products.use_predefined',
                'labels.view', 'labels.manage', 'labels.print',
                'reports.view', 'reports.inventory', 'reports.export',
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],

        [
            'name'            => 'branch_kitchen_staff',
            'display_name'    => 'Branch Kitchen Staff',
            'display_name_ar' => 'طاقم مطبخ الفرع',
            'scope'           => 'branch',
            'description'     => 'View and process kitchen orders for a single branch',
            'description_ar'  => 'عرض ومعالجة طلبات المطبخ لفرع واحد',
            'permissions'     => [
                'orders.view', 'orders.update_status',
                'restaurant.kds', 'restaurant.tables', 'restaurant.view',
                'bakery.view', 'bakery.production',
                'notifications.view',
            ],
        ],

        [
            'name'            => 'senior_cashier',
            'display_name'    => 'Senior Cashier',
            'display_name_ar' => 'كاشير أول',
            'scope'           => 'branch',
            'description'     => 'Cashier with discount, refund, and void privileges for a single branch',
            'description_ar'  => 'كاشير مع صلاحيات الخصم والاسترجاع والإلغاء لفرع واحد',
            'permissions'     => [
                // POS (advanced)
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.discount', 'pos.void',
                'pos.refund', 'pos.return', 'pos.hold_recall', 'pos.reprint_receipt',
                'pos.price_override', 'pos.view_sessions',
                // Orders
                'orders.view', 'orders.manage', 'orders.return', 'orders.update_status',
                // Transactions
                'transactions.view',
                // Products
                'products.view',
                // Customers
                'customers.view', 'customers.manage', 'customers.manage_loyalty',
                // Payments
                'payments.process', 'payments.refund',
                // Cash
                'cash.manage', 'cash.view_sessions', 'cash.view_daily_summary',
                // Installments
                'installments.use', 'installments.view_history',
                // Promotions
                'promotions.apply_manual',
                // Labels
                'labels.print',
                // Dashboard
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],

        [
            'name'            => 'cashier',
            'display_name'    => 'Cashier',
            'display_name_ar' => 'كاشير',
            'scope'           => 'branch',
            'description'     => 'Process sales and manage the register for a single branch',
            'description_ar'  => 'معالجة المبيعات وإدارة الصندوق لفرع واحد',
            'permissions'     => [
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.hold_recall',
                'pos.reprint_receipt', 'pos.return', 'pos.view_sessions',
                'orders.view', 'orders.manage', 'orders.update_status',
                'transactions.view',
                'products.view',
                'customers.view', 'customers.manage',
                'payments.process',
                'cash.manage', 'cash.view_sessions',
                // Installments
                'installments.use',
                'labels.print',
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],

        [
            'name'            => 'branch_viewer',
            'display_name'    => 'Branch Viewer',
            'display_name_ar' => 'مشاهد الفرع',
            'scope'           => 'branch',
            'description'     => 'Read-only access to branch dashboards, reports, and data',
            'description_ar'  => 'صلاحية العرض فقط للوحات المعلومات والتقارير والبيانات للفرع',
            'permissions'     => [
                'dashboard.view',
                'reports.view', 'reports.sales', 'reports.inventory', 'reports.customers',
                'orders.view', 'products.view', 'inventory.view', 'customers.view',
                'transactions.view',
                'pos.view_sessions', 'cash.view_sessions', 'cash.view_daily_summary',
                'installments.view_history',
                'labels.view', 'promotions.view_analytics',
                'notifications.view',
                'support.view',
                // Wameed AI
                'wameed_ai.view',
                // Cashier Gamification
                'cashier_performance.view_leaderboard', 'cashier_performance.view_badges',
                'cashier_performance.view_anomalies', 'cashier_performance.view_reports',
            ],
        ],

        [
            'name'            => 'branch_sales_manager',
            'display_name'    => 'Branch Sales Manager',
            'display_name_ar' => 'مدير مبيعات الفرع',
            'scope'           => 'branch',
            'description'     => 'Sales operations, customer management, and promotions for a single branch',
            'description_ar'  => 'عمليات المبيعات وإدارة العملاء والعروض الترويجية لفرع واحد',
            'permissions'     => [
                // POS
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.discount', 'pos.approve_discount',
                'pos.void', 'pos.void_transaction', 'pos.edit_transaction', 'pos.refund', 'pos.return',
                'pos.hold_recall', 'pos.reprint_receipt', 'pos.price_override', 'pos.view_sessions',
                // Orders
                'orders.view', 'orders.manage', 'orders.return', 'orders.void', 'orders.update_status',
                // Transactions
                'transactions.view', 'transactions.export', 'transactions.void',
                // Products (view + pricing)
                'products.view', 'products.manage_pricing',
                // Customers
                'customers.view', 'customers.manage', 'customers.manage_loyalty',
                'customers.manage_credit', 'customers.manage_debits',
                // Payments
                'payments.process', 'payments.refund',
                // Cash
                'cash.manage', 'cash.view_sessions', 'cash.view_daily_summary',
                // Installments
                'installments.use', 'installments.view_history',
                // Finance
                'finance.commissions', 'finance.gift_cards',
                // Promotions
                'promotions.manage', 'promotions.apply_manual', 'promotions.view_analytics',
                // Reports
                'reports.view', 'reports.sales', 'reports.customers', 'reports.export',
                // Labels
                'labels.view', 'labels.print',
                // Dashboard
                'dashboard.view',
                'notifications.view', 'notifications.manage',
                'support.view', 'support.create_ticket',
                // Wameed AI
                'wameed_ai.view', 'wameed_ai.use',
                // Cashier Gamification
                'cashier_performance.view_leaderboard', 'cashier_performance.view_badges',
                'cashier_performance.view_anomalies', 'cashier_performance.view_reports',
            ],
        ],
    ];
}
