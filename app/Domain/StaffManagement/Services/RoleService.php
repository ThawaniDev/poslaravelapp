<?php

namespace App\Domain\StaffManagement\Services;

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
     */
    public const DEFAULT_ROLE_TEMPLATES = [
        [
            'name'         => 'owner',
            'display_name' => 'Owner',
            'display_name_ar' => 'المالك',
            'description'  => 'Full access to all features and settings',
            'description_ar' => 'وصول كامل لجميع الميزات والإعدادات',
            'permissions'  => ['*'], // handled specially — gets ALL permissions
        ],
        [
            'name'         => 'branch_manager',
            'display_name' => 'Branch Manager',
            'display_name_ar' => 'مدير الفرع',
            'description'  => 'Manage store operations, staff, and reports',
            'description_ar' => 'إدارة عمليات المتجر والموظفين والتقارير',
            'permissions'  => [
                // POS
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.discount', 'pos.void_transaction',
                'pos.hold_recall', 'pos.refund', 'pos.return', 'pos.reprint_receipt', 'pos.view_sessions',
                'pos.manage_terminals', 'pos.price_override', 'pos.no_sale',
                // Orders
                'orders.view', 'orders.manage', 'orders.return', 'orders.void', 'orders.update_status',
                // Products
                'products.view', 'products.manage', 'products.manage_categories', 'products.manage_suppliers',
                'products.import_export', 'products.manage_pricing',
                // Inventory
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
                'inventory.stocktake', 'inventory.receive', 'inventory.purchase_orders',
                // Customers
                'customers.view', 'customers.manage', 'customers.manage_loyalty', 'customers.manage_debits',
                // Payments & Cash
                'payments.process', 'payments.refund',
                'cash.manage', 'cash.view_sessions', 'cash.view_daily_summary', 'cash.reconciliation',
                // Reports
                'reports.view', 'reports.sales', 'reports.inventory', 'reports.staff',
                'reports.customers', 'reports.attendance', 'reports.export',
                // Staff
                'staff.view', 'staff.create', 'staff.edit', 'staff.manage', 'staff.manage_shifts',
                'roles.view', 'roles.assign',
                // Labels & Promotions
                'labels.view', 'labels.manage', 'labels.print',
                'promotions.manage', 'promotions.apply_manual', 'promotions.view_analytics',
                // Settings (view only)
                'settings.view', 'settings.localization',
                // Dashboard & Notifications
                'dashboard.view',
                'notifications.view', 'notifications.manage',
                // Support
                'support.view', 'support.create_ticket',
            ],
        ],
        [
            'name'         => 'cashier',
            'display_name' => 'Cashier',
            'display_name_ar' => 'كاشير',
            'description'  => 'Process sales and manage the register',
            'description_ar' => 'معالجة المبيعات وإدارة الصندوق',
            'permissions'  => [
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.hold_recall',
                'pos.reprint_receipt', 'pos.return', 'pos.view_sessions',
                'orders.view', 'orders.manage', 'orders.update_status',
                'products.view',
                'customers.view', 'customers.manage',
                'payments.process',
                'cash.manage', 'cash.view_sessions',
                'labels.print',
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],
        [
            'name'         => 'senior_cashier',
            'display_name' => 'Senior Cashier',
            'display_name_ar' => 'كاشير أول',
            'description'  => 'Cashier with discount and refund privileges',
            'description_ar' => 'كاشير مع صلاحيات الخصم والاسترجاع',
            'permissions'  => [
                'pos.shift_open', 'pos.shift_close', 'pos.sell', 'pos.hold_recall',
                'pos.reprint_receipt', 'pos.return', 'pos.view_sessions',
                'pos.discount', 'pos.refund', 'pos.void', 'pos.price_override',
                'orders.view', 'orders.manage', 'orders.return', 'orders.update_status',
                'products.view',
                'customers.view', 'customers.manage', 'customers.manage_loyalty',
                'payments.process', 'payments.refund',
                'cash.manage', 'cash.view_sessions', 'cash.view_daily_summary',
                'labels.print',
                'promotions.apply_manual',
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],
        [
            'name'         => 'inventory_clerk',
            'display_name' => 'Inventory Clerk',
            'display_name_ar' => 'أمين المخزون',
            'description'  => 'Manage stock and inventory',
            'description_ar' => 'إدارة المخزون والبضائع',
            'permissions'  => [
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer',
                'inventory.stocktake', 'inventory.receive', 'inventory.purchase_orders',
                'inventory.supplier_returns', 'inventory.recipes',
                'products.view', 'products.manage', 'products.manage_categories', 'products.manage_suppliers',
                'labels.view', 'labels.manage', 'labels.print',
                'reports.inventory',
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],
        [
            'name'         => 'accountant',
            'display_name' => 'Accountant',
            'display_name_ar' => 'محاسب',
            'description'  => 'Financial reporting and cash management',
            'description_ar' => 'التقارير المالية وإدارة النقد',
            'permissions'  => [
                'reports.view', 'reports.sales', 'reports.view_financial', 'reports.view_margin',
                'reports.inventory', 'reports.customers', 'reports.export',
                'accounting.configure', 'accounting.view_history', 'accounting.export', 'accounting.manage_mappings',
                'finance.commissions', 'finance.settlements', 'finance.expenses', 'finance.gift_cards',
                'cash.view_sessions', 'cash.view_daily_summary', 'cash.reconciliation',
                'orders.view',
                'pos.view_sessions',
                'customers.view', 'customers.manage_debits',
                'dashboard.view',
                'notifications.view',
                'support.view', 'support.create_ticket',
            ],
        ],
        [
            'name'         => 'kitchen_staff',
            'display_name' => 'Kitchen Staff',
            'display_name_ar' => 'طاقم المطبخ',
            'description'  => 'View and process kitchen orders',
            'description_ar' => 'عرض ومعالجة طلبات المطبخ',
            'permissions'  => [
                'orders.view', 'orders.update_status',
                'restaurant.kds', 'restaurant.tables', 'restaurant.view',
                'notifications.view',
            ],
        ],
        [
            'name'         => 'viewer',
            'display_name' => 'Viewer',
            'display_name_ar' => 'مشاهد',
            'description'  => 'Read-only access to reports and dashboards',
            'description_ar' => 'صلاحية العرض فقط للتقارير ولوحات المعلومات',
            'permissions'  => [
                'dashboard.view',
                'reports.view', 'reports.sales', 'reports.inventory', 'reports.customers',
                'orders.view',
                'products.view',
                'inventory.view',
                'customers.view',
                'notifications.view',
            ],
        ],
    ];
}
