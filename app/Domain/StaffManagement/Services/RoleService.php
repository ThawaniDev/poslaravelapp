<?php

namespace App\Domain\StaffManagement\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Security\Enums\RoleAuditAction;
use App\Domain\Security\Models\RoleAuditLog;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * List all roles for a store.
     */
    public function listForStore(string $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return Role::forStore($storeId)
            ->with('permissions')
            ->orderBy('is_predefined', 'desc')
            ->orderBy('name')
            ->get();
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

        foreach ($templates as $template) {
            $role = Role::create([
                'store_id'      => $storeId,
                'name'          => $template['name'],
                'display_name'  => $template['display_name'],
                'guard_name'    => 'staff',
                'is_predefined' => true,
                'description'   => $template['description'] ?? null,
            ]);

            // Attach permissions by name
            if (!empty($template['permissions'])) {
                $permIds = Permission::whereIn('name', $template['permissions'])->pluck('id');
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
     */
    public const DEFAULT_ROLE_TEMPLATES = [
        [
            'name'         => 'owner',
            'display_name' => 'Owner',
            'description'  => 'Full access to all features and settings',
            'permissions'  => ['*'], // handled specially — gets ALL permissions
        ],
        [
            'name'         => 'branch_manager',
            'display_name' => 'Branch Manager',
            'description'  => 'Manage store operations, staff, and reports',
            'permissions'  => [
                'pos.open_session', 'pos.close_session', 'pos.sell', 'pos.apply_discount', 'pos.void_transaction',
                'pos.hold_recall', 'pos.refund', 'pos.reprint_receipt',
                'orders.view', 'orders.create', 'orders.update_status', 'orders.cancel',
                'inventory.view', 'inventory.adjust', 'inventory.receive', 'inventory.transfer',
                'catalog.view', 'catalog.create', 'catalog.update', 'catalog.manage_categories',
                'customers.view', 'customers.create', 'customers.update',
                'reports.view_sales', 'reports.view_inventory', 'reports.view_staff',
                'staff.view', 'staff.create', 'staff.update', 'staff.assign_role',
                'settings.view',
            ],
        ],
        [
            'name'         => 'cashier',
            'display_name' => 'Cashier',
            'description'  => 'Process sales and manage the register',
            'permissions'  => [
                'pos.open_session', 'pos.close_session', 'pos.sell', 'pos.hold_recall', 'pos.reprint_receipt',
                'orders.view', 'orders.create',
                'catalog.view',
                'customers.view', 'customers.create',
            ],
        ],
        [
            'name'         => 'inventory_clerk',
            'display_name' => 'Inventory Clerk',
            'description'  => 'Manage stock and inventory',
            'permissions'  => [
                'inventory.view', 'inventory.adjust', 'inventory.receive', 'inventory.transfer', 'inventory.count',
                'catalog.view', 'catalog.create', 'catalog.update',
                'reports.view_inventory',
            ],
        ],
        [
            'name'         => 'accountant',
            'display_name' => 'Accountant',
            'description'  => 'Financial reporting and cash management',
            'permissions'  => [
                'reports.view_sales', 'reports.view_financial', 'reports.export',
                'accounting.view', 'accounting.manage_expenses',
                'orders.view',
                'pos.view_sessions',
            ],
        ],
        [
            'name'         => 'kitchen_staff',
            'display_name' => 'Kitchen Staff',
            'description'  => 'View and process kitchen orders',
            'permissions'  => [
                'orders.view', 'orders.update_status',
                'kitchen.view', 'kitchen.update_status',
            ],
        ],
    ];
}
