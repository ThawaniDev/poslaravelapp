<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Services\PlatformRoleService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\CreateAdminRoleRequest;
use App\Http\Requests\Admin\CreateAdminTeamUserRequest;
use App\Http\Requests\Admin\UpdateAdminRoleRequest;
use App\Http\Requests\Admin\UpdateAdminTeamUserRequest;
use App\Http\Resources\Admin\AdminActivityLogResource;
use App\Http\Resources\Admin\AdminRoleResource;
use App\Http\Resources\Admin\AdminTeamUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformRoleController extends BaseApiController
{
    public function __construct(
        private readonly PlatformRoleService $service
    ) {}

    // ─── Roles ───────────────────────────────────────────────

    /**
     * GET /admin/roles
     */
    public function listRoles(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['admin_team.roles', 'admin_team.view', 'admin_team.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $roles = $this->service->listRoles();

        return $this->success([
            'roles' => AdminRoleResource::collection($roles),
        ]);
    }

    /**
     * GET /admin/roles/{roleId}
     */
    public function showRole(Request $request, string $roleId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['admin_team.roles', 'admin_team.view', 'admin_team.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $role = $this->service->getRole($roleId);

        if (!$role) {
            return $this->notFound('Role not found');
        }

        return $this->success([
            'role' => new AdminRoleResource($role),
        ]);
    }

    /**
     * POST /admin/roles
     */
    public function createRole(CreateAdminRoleRequest $request): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.roles')) {
            return $this->error('Forbidden', 403);
        }

        $role = $this->service->createRole(
            $request->validated(),
            $request->user()->id
        );

        return $this->created([
            'role' => new AdminRoleResource($role),
        ], 'Role created successfully');
    }

    /**
     * PUT /admin/roles/{roleId}
     */
    public function updateRole(UpdateAdminRoleRequest $request, string $roleId): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.roles')) {
            return $this->error('Forbidden', 403);
        }

        $role = AdminRole::find($roleId);

        if (!$role) {
            return $this->notFound('Role not found');
        }

        if ($role->is_system && isset($request->validated()['name'])) {
            return $this->error('Cannot rename system roles', 422);
        }

        $updatedRole = $this->service->updateRole(
            $role,
            $request->validated(),
            $request->user()->id
        );

        return $this->success([
            'role' => new AdminRoleResource($updatedRole),
        ], 'Role updated successfully');
    }

    /**
     * DELETE /admin/roles/{roleId}
     */
    public function deleteRole(Request $request, string $roleId): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.roles')) {
            return $this->error('Forbidden', 403);
        }

        $role = AdminRole::find($roleId);

        if (!$role) {
            return $this->notFound('Role not found');
        }

        if ($role->is_system) {
            return $this->error('Cannot delete system roles', 422);
        }

        $deleted = $this->service->deleteRole($role, $request->user()->id);

        if (!$deleted) {
            return $this->error('Cannot delete role with assigned users. Reassign users first.', 422);
        }

        return $this->success(null, 'Role deleted successfully');
    }

    // ─── Permissions ─────────────────────────────────────────

    /**
     * GET /admin/permissions
     */
    public function listPermissions(): JsonResponse
    {
        $grouped = $this->service->listPermissionsGrouped();

        return $this->success([
            'permissions' => $grouped,
            'total' => array_sum(array_map('count', $grouped)),
        ]);
    }

    // ─── Admin Team ──────────────────────────────────────────

    /**
     * GET /admin/team
     */
    public function listTeam(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['admin_team.view', 'admin_team.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $filters = $request->only(['search', 'is_active', 'role_id']);

        if (isset($filters['is_active'])) {
            $filters['is_active'] = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $users = $this->service->listAdminUsers($filters, (int) $request->get('per_page', 15));

        return $this->success([
            'users' => AdminTeamUserResource::collection($users->items()),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/team/{userId}
     */
    public function showTeamUser(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasAnyPermission(['admin_team.view', 'admin_team.manage'])) {
            return $this->error('Forbidden', 403);
        }

        $user = $this->service->getAdminUser($userId);

        if (!$user) {
            return $this->notFound('Admin user not found');
        }

        return $this->success([
            'user' => new AdminTeamUserResource($user),
        ]);
    }

    /**
     * POST /admin/team
     */
    public function createTeamUser(CreateAdminTeamUserRequest $request): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.manage')) {
            return $this->error('Forbidden', 403);
        }

        $user = $this->service->createAdminUser(
            $request->validated(),
            $request->user()->id
        );

        return $this->created([
            'user' => new AdminTeamUserResource($user),
        ], 'Admin user created successfully');
    }

    /**
     * PUT /admin/team/{userId}
     */
    public function updateTeamUser(UpdateAdminTeamUserRequest $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.manage')) {
            return $this->error('Forbidden', 403);
        }

        $user = AdminUser::find($userId);

        if (!$user) {
            return $this->notFound('Admin user not found');
        }

        $updatedUser = $this->service->updateAdminUser(
            $user,
            $request->validated(),
            $request->user()->id
        );

        return $this->success([
            'user' => new AdminTeamUserResource($updatedUser),
        ], 'Admin user updated successfully');
    }

    /**
     * POST /admin/team/{userId}/deactivate
     */
    public function deactivateTeamUser(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.manage')) {
            return $this->error('Forbidden', 403);
        }

        $user = AdminUser::find($userId);

        if (!$user) {
            return $this->notFound('Admin user not found');
        }

        if ($user->id === $request->user()->id) {
            return $this->error('Cannot deactivate your own account', 422);
        }

        $deactivated = $this->service->deactivateAdminUser($user, $request->user()->id);

        return $this->success([
            'user' => new AdminTeamUserResource($deactivated),
        ], 'Admin user deactivated');
    }

    /**
     * POST /admin/team/{userId}/activate
     */
    public function activateTeamUser(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->hasPermission('admin_team.manage')) {
            return $this->error('Forbidden', 403);
        }

        $user = AdminUser::find($userId);

        if (!$user) {
            return $this->notFound('Admin user not found');
        }

        $activated = $this->service->activateAdminUser($user, $request->user()->id);

        return $this->success([
            'user' => new AdminTeamUserResource($activated),
        ], 'Admin user activated');
    }

    // ─── Profile ─────────────────────────────────────────────

    /**
     * GET /admin/me
     */
    public function me(Request $request): JsonResponse
    {
        $profile = $this->service->getAdminUserProfile($request->user());

        return $this->success(['profile' => $profile]);
    }

    // ─── Activity Log ────────────────────────────────────────

    /**
     * GET /admin/activity-log
     */
    public function listActivityLog(Request $request): JsonResponse
    {
        $filters = $request->only([
            'admin_user_id', 'action', 'entity_type', 'entity_id',
            'date_from', 'date_to',
        ]);

        $logs = $this->service->listActivityLogs(
            $filters,
            (int) $request->get('per_page', 20)
        );

        return $this->success([
            'logs' => AdminActivityLogResource::collection($logs->items()),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
