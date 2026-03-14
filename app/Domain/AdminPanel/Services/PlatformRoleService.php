<?php

namespace App\Domain\AdminPanel\Services;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PlatformRoleService
{
    // ─── Roles ───────────────────────────────────────────────

    public function listRoles(): Collection
    {
        return AdminRole::query()
            ->withCount(['adminUserRoles', 'adminRolePermissions'])
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();
    }

    public function getRole(string $roleId): ?AdminRole
    {
        return AdminRole::query()
            ->with(['adminRolePermissions.adminPermission'])
            ->withCount(['adminUserRoles', 'adminRolePermissions'])
            ->find($roleId);
    }

    public function createRole(array $data, string $adminId): AdminRole
    {
        $role = AdminRole::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name'], '_'),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        if (!empty($data['permission_ids'])) {
            foreach ($data['permission_ids'] as $permId) {
                AdminRolePermission::create([
                    'admin_role_id' => $role->id,
                    'admin_permission_id' => $permId,
                ]);
            }
        }

        $this->logActivity($adminId, 'role.create', 'admin_role', $role->id, [
            'name' => $role->name,
            'slug' => $role->slug,
            'permission_count' => count($data['permission_ids'] ?? []),
        ]);

        return $role->load('adminRolePermissions.adminPermission')
            ->loadCount(['adminUserRoles', 'adminRolePermissions']);
    }

    public function updateRole(AdminRole $role, array $data, string $adminId): AdminRole
    {
        $oldValues = $role->only(['name', 'description']);

        if (isset($data['name']) && !$role->is_system) {
            $role->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $role->description = $data['description'];
        }
        $role->save();

        if (isset($data['permission_ids'])) {
            AdminRolePermission::where('admin_role_id', $role->id)->delete();
            foreach ($data['permission_ids'] as $permId) {
                AdminRolePermission::create([
                    'admin_role_id' => $role->id,
                    'admin_permission_id' => $permId,
                ]);
            }
        }

        $this->logActivity($adminId, 'role.update', 'admin_role', $role->id, [
            'old' => $oldValues,
            'new' => $role->only(['name', 'description']),
            'permission_ids' => $data['permission_ids'] ?? null,
        ]);

        return $role->fresh()
            ->load('adminRolePermissions.adminPermission')
            ->loadCount(['adminUserRoles', 'adminRolePermissions']);
    }

    public function deleteRole(AdminRole $role, string $adminId): bool
    {
        if ($role->is_system) {
            return false;
        }

        $assignedCount = AdminUserRole::where('admin_role_id', $role->id)->count();
        if ($assignedCount > 0) {
            return false;
        }

        $this->logActivity($adminId, 'role.delete', 'admin_role', $role->id, [
            'name' => $role->name,
            'slug' => $role->slug,
        ]);

        AdminRolePermission::where('admin_role_id', $role->id)->delete();
        $role->delete();

        return true;
    }

    // ─── Permissions ─────────────────────────────────────────

    public function listPermissions(): Collection
    {
        return AdminPermission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get();
    }

    public function listPermissionsGrouped(): array
    {
        $permissions = $this->listPermissions();
        $grouped = [];

        foreach ($permissions as $perm) {
            $group = $perm->group instanceof \BackedEnum ? $perm->group->value : $perm->group;
            $grouped[$group][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'description' => $perm->description,
            ];
        }

        return $grouped;
    }

    // ─── Admin Team ──────────────────────────────────────────

    public function listAdminUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AdminUser::query()
            ->withCount('adminUserRoles');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['role_id'])) {
            $query->whereHas('adminUserRoles', function ($q) use ($filters) {
                $q->where('admin_role_id', $filters['role_id']);
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function getAdminUser(string $userId): ?AdminUser
    {
        return AdminUser::query()
            ->with(['adminUserRoles.adminRole.adminRolePermissions.adminPermission'])
            ->find($userId);
    }

    public function createAdminUser(array $data, string $createdBy): AdminUser
    {
        $user = AdminUser::forceCreate([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => bcrypt($data['password']),
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (!empty($data['role_ids'])) {
            foreach ($data['role_ids'] as $roleId) {
                AdminUserRole::create([
                    'admin_user_id' => $user->id,
                    'admin_role_id' => $roleId,
                    'assigned_at' => now(),
                    'assigned_by' => $createdBy,
                ]);
            }
        }

        $this->logActivity($createdBy, 'admin_user.create', 'admin_user', $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role_ids' => $data['role_ids'] ?? [],
        ]);

        return $user->load('adminUserRoles.adminRole');
    }

    public function updateAdminUser(AdminUser $user, array $data, string $updatedBy): AdminUser
    {
        $oldValues = $user->only(['name', 'is_active']);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }
        if (isset($data['is_active'])) {
            $user->is_active = $data['is_active'];
        }
        $user->save();

        if (isset($data['role_ids'])) {
            AdminUserRole::where('admin_user_id', $user->id)->delete();
            foreach ($data['role_ids'] as $roleId) {
                AdminUserRole::create([
                    'admin_user_id' => $user->id,
                    'admin_role_id' => $roleId,
                    'assigned_at' => now(),
                    'assigned_by' => $updatedBy,
                ]);
            }
        }

        $this->logActivity($updatedBy, 'admin_user.update', 'admin_user', $user->id, [
            'old' => $oldValues,
            'new' => $user->only(['name', 'is_active']),
            'role_ids' => $data['role_ids'] ?? null,
        ]);

        return $user->fresh()->load('adminUserRoles.adminRole');
    }

    public function deactivateAdminUser(AdminUser $user, string $deactivatedBy): AdminUser
    {
        $user->update(['is_active' => false]);

        $this->logActivity($deactivatedBy, 'admin_user.deactivate', 'admin_user', $user->id, [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return $user->fresh();
    }

    public function activateAdminUser(AdminUser $user, string $activatedBy): AdminUser
    {
        $user->update(['is_active' => true]);

        $this->logActivity($activatedBy, 'admin_user.activate', 'admin_user', $user->id, [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return $user->fresh();
    }

    public function getAdminUserProfile(AdminUser $user): array
    {
        $user = $user->fresh('adminUserRoles.adminRole.adminRolePermissions.adminPermission');

        $roles = $user->adminUserRoles->map(fn ($ur) => $ur->adminRole);
        $permissions = $roles->flatMap(function ($role) {
            return $role->adminRolePermissions->map(fn ($rp) => $rp->adminPermission->name);
        })->unique()->values()->toArray();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'is_active' => $user->is_active,
            'two_factor_enabled' => $user->two_factor_enabled,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'roles' => $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug instanceof \BackedEnum ? $r->slug->value : $r->slug,
            ])->values()->toArray(),
            'permissions' => $permissions,
        ];
    }

    // ─── Activity Logs ───────────────────────────────────────

    public function listActivityLogs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AdminActivityLog::query()
            ->with('adminUser');

        if (!empty($filters['admin_user_id'])) {
            $query->where('admin_user_id', $filters['admin_user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function logActivity(
        string $adminUserId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AdminActivityLog {
        return AdminActivityLog::forceCreate([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $ipAddress ?? '127.0.0.1',
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
