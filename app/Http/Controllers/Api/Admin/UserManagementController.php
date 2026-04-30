<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use App\Domain\Auth\Models\User;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\InviteAdminRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Resources\Admin\ActivityLogResource;
use App\Http\Resources\Admin\AdminUserDetailResource;
use App\Http\Resources\Admin\ProviderUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementController extends BaseApiController
{
    // ═══════════════════════════════════════════════════════════════
    // User Stats
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /admin/users/stats
     * Aggregate user statistics for KPI cards.
     */
    public function stats(): JsonResponse
    {
        $totalProviders = User::count();
        $activeProviders = User::where('is_active', true)->count();
        $inactiveProviders = User::where('is_active', false)->count();
        $newThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();
        $totalAdmins = AdminUser::count();
        $activeAdmins = AdminUser::where('is_active', true)->count();

        $roleDistribution = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        $storeDistribution = User::selectRaw('store_id, COUNT(*) as user_count')
            ->groupBy('store_id')
            ->orderByDesc('user_count')
            ->limit(10)
            ->get();

        return $this->success([
            'total_provider_users' => $totalProviders,
            'active_provider_users' => $activeProviders,
            'inactive_provider_users' => $inactiveProviders,
            'new_this_month' => $newThisMonth,
            'total_admin_users' => $totalAdmins,
            'active_admin_users' => $activeAdmins,
            'role_distribution' => $roleDistribution,
            'top_stores_by_users' => $storeDistribution,
        ], 'User stats retrieved');
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider Users
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /admin/users/provider
     * List all provider users with cross-store search.
     */
    public function listProviderUsers(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['users.view', 'users.manage', 'users.edit'])) {
            return $this->error('Forbidden', 403);
        }

        $query = User::query()->with(['store', 'organization']);

        // Search by name, email, or phone
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by organization
        if ($orgId = $request->get('organization_id')) {
            $query->where('organization_id', $orgId);
        }

        // Filter by store
        if ($storeId = $request->get('store_id')) {
            $query->where('store_id', $storeId);
        }

        // Filter by role
        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->orderBy('created_at', 'desc')
                       ->paginate((int) $request->get('per_page', 15));

        return $this->success([
            'users' => ProviderUserResource::collection($users->items()),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/users/provider/{userId}
     * Show a single provider user with relationships.
     */
    public function showProviderUser(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['users.view', 'users.manage', 'users.edit'])) {
            return $this->error('Forbidden', 403);
        }

        $user = User::with(['store', 'organization'])->find($userId);

        if (!$user) {
            return $this->notFound('Provider user not found');
        }

        return $this->success(new ProviderUserResource($user));
    }

    /**
     * POST /admin/users/provider/{userId}/reset-password
     * Generate a temporary password and return it.
     */
    public function resetPassword(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['users.reset_password', 'users.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return $this->notFound('Provider user not found');
        }

        $tempPassword = Str::random(12);
        $user->update(['password_hash' => Hash::make($tempPassword), 'must_change_password' => true]);

        // Log the action
        $this->logActivity('reset_password', 'user', $user->id, [
            'user_email' => $user->email,
        ]);

        return $this->success([
            'temporary_password' => $tempPassword,
            'must_change_password' => true,
        ], 'Password reset successfully');
    }

    /**
     * POST /admin/users/provider/{userId}/force-password-change
     * Set must_change_password flag.
     */
    public function forcePasswordChange(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['users.manage', 'users.edit'])) {
            return $this->error('Forbidden', 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return $this->notFound('Provider user not found');
        }

        $user->update(['must_change_password' => true]);

        $this->logActivity('force_password_change', 'user', $user->id, [
            'user_email' => $user->email,
        ]);

        return $this->success(new ProviderUserResource($user->fresh()), 'Password change enforced');
    }

    /**
     * POST /admin/users/provider/{userId}/toggle-active
     * Enable or disable a provider user account.
     */
    public function toggleProviderActive(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['users.edit', 'users.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return $this->notFound('Provider user not found');
        }

        $newStatus = !$user->is_active;
        $user->update(['is_active' => $newStatus]);

        $action = $newStatus ? 'user_enabled' : 'user_disabled';
        $this->logActivity($action, 'user', $user->id, [
            'user_email' => $user->email,
            'new_status' => $newStatus,
        ]);

        return $this->success(
            new ProviderUserResource($user->fresh(['store', 'organization'])),
            $newStatus ? 'User account enabled' : 'User account disabled'
        );
    }

    /**
     * GET /admin/users/provider/{userId}/activity
     * View activity log for a specific provider user.
     */
    public function providerUserActivity(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['users.view', 'users.manage', 'users.edit'])) {
            return $this->error('Forbidden', 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return $this->notFound('Provider user not found');
        }

        $logs = AdminActivityLog::where('entity_type', 'user')
            ->where('entity_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return $this->success([
            'user_id' => $userId,
            'logs' => ActivityLogResource::collection($logs),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // Admin Users (Platform Team)
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /admin/users/admins
     * List all admin users with roles.
     */
    public function listAdminUsers(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['admin_team.view', 'admin_team.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $query = AdminUser::query()->with('adminUserRoles.adminRole');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $admins = $query->orderBy('name')->get();

        return $this->success([
            'admins' => AdminUserDetailResource::collection($admins),
        ]);
    }

    /**
     * GET /admin/users/admins/{userId}
     * Show a single admin user with roles and details.
     */
    public function showAdminUser(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['admin_team.view', 'admin_team.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $admin = AdminUser::with('adminUserRoles.adminRole')->find($userId);

        if (!$admin) {
            return $this->notFound('Admin user not found');
        }

        return $this->success(new AdminUserDetailResource($admin));
    }

    /**
     * POST /admin/users/admins
     * Create/invite a new admin user.
     */
    public function inviteAdmin(InviteAdminRequest $request): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.manage')) {
            return $this->error('Forbidden', 403);
        }

        $tempPassword = Str::random(16);

        $admin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password_hash' => Hash::make($tempPassword),
            'is_active' => $request->get('is_active', true),
        ]);

        // Assign roles
        $currentAdmin = $request->user();
        foreach ($request->role_ids as $roleId) {
            AdminUserRole::forceCreate([
                'admin_user_id' => $admin->id,
                'admin_role_id' => $roleId,
                'assigned_at' => now(),
                'assigned_by' => $currentAdmin?->id,
            ]);
        }

        $admin = $admin->fresh(['adminUserRoles.adminRole']);

        $this->logActivity('admin_invited', 'admin_user', $admin->id, [
            'email' => $admin->email,
            'roles' => $request->role_ids,
        ]);

        return $this->created(new AdminUserDetailResource($admin), 'Admin user invited successfully');
    }

    /**
     * PUT /admin/users/admins/{userId}
     * Update an admin user.
     */
    public function updateAdmin(UpdateAdminUserRequest $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.manage')) {
            return $this->error('Forbidden', 403);
        }

        $admin = AdminUser::find($userId);

        if (!$admin) {
            return $this->notFound('Admin user not found');
        }

        // Self-edit restriction: cannot deactivate own account
        $currentAdmin = $request->user();
        if ($currentAdmin && $currentAdmin->id === $admin->id && $request->has('is_active') && !$request->is_active) {
            return $this->error('Cannot deactivate your own account', 422);
        }

        $updateData = $request->only(['name', 'phone', 'is_active']);
        $admin->update(array_filter($updateData, fn ($v) => $v !== null));

        // Update roles if provided
        if ($request->has('role_ids')) {
            AdminUserRole::where('admin_user_id', $admin->id)->delete();
            foreach ($request->role_ids as $roleId) {
                AdminUserRole::forceCreate([
                    'admin_user_id' => $admin->id,
                    'admin_role_id' => $roleId,
                    'assigned_at' => now(),
                    'assigned_by' => $currentAdmin?->id,
                ]);
            }
        }

        $admin = $admin->fresh(['adminUserRoles.adminRole']);

        $this->logActivity('admin_updated', 'admin_user', $admin->id, [
            'changes' => $updateData,
        ]);

        return $this->success(new AdminUserDetailResource($admin), 'Admin user updated');
    }

    /**
     * POST /admin/users/admins/{userId}/reset-2fa
     * Clear 2FA secret for an admin user.
     */
    public function resetAdmin2fa(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.manage')) {
            return $this->error('Forbidden', 403);
        }

        $admin = AdminUser::find($userId);

        if (!$admin) {
            return $this->notFound('Admin user not found');
        }

        $admin->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        $this->logActivity('admin_2fa_reset', 'admin_user', $admin->id, [
            'email' => $admin->email,
        ]);

        return $this->success(
            new AdminUserDetailResource($admin->fresh(['adminUserRoles.adminRole'])),
            '2FA reset successfully'
        );
    }

    /**
     * GET /admin/users/admins/{userId}/activity
     * View activity log for a specific admin user.
     */
    public function adminUserActivity(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['admin_team.view', 'admin_team.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $admin = AdminUser::find($userId);

        if (!$admin) {
            return $this->notFound('Admin user not found');
        }

        $logs = AdminActivityLog::where(function ($q) use ($userId) {
            $q->where('admin_user_id', $userId)
              ->orWhere(function ($q2) use ($userId) {
                  $q2->where('entity_type', 'admin_user')
                     ->where('entity_id', $userId);
              });
        })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return $this->success([
            'admin_user_id' => $userId,
            'logs' => ActivityLogResource::collection($logs),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════

    private function logActivity(string $action, string $entityType, string $entityId, array $details = []): void
    {
        AdminActivityLog::forceCreate([
            'id' => (string) Str::uuid(),
            'admin_user_id' => auth('admin-api')->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ?: null,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
