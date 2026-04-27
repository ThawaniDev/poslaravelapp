<?php

namespace App\Domain\StaffManagement\Controllers\Api;

use App\Domain\StaffManagement\Requests\AssignRoleRequest;
use App\Domain\StaffManagement\Requests\CreateRoleRequest;
use App\Domain\StaffManagement\Requests\UpdateRoleRequest;
use App\Domain\StaffManagement\Resources\RoleResource;
use App\Domain\StaffManagement\Services\RoleService;
use App\Domain\Auth\Models\User;
use App\Domain\Security\Models\RoleAuditLog;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

class RoleController extends BaseApiController
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    /**
     * GET /api/v2/staff/roles
     */
    public function index(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;

        $roles = $this->roleService->listForStore($storeId);

        return $this->success(RoleResource::collection($roles));
    }

    /**
     * GET /api/v2/staff/roles/{role}
     */
    public function show(string $role)
    {
        $found = $this->roleService->find($role);

        return $this->success(new RoleResource($found));
    }

    /**
     * POST /api/v2/staff/roles
     */
    public function store(CreateRoleRequest $request)
    {
        $role = $this->roleService->create(
            $request->validated(),
            $request->user(),
        );

        return $this->created(new RoleResource($role));
    }

    /**
     * PUT /api/v2/staff/roles/{role}
     */
    public function update(UpdateRoleRequest $request, string $role)
    {
        $found = $this->roleService->find($role);

        $updated = $this->roleService->update(
            $found,
            $request->validated(),
            $request->user(),
        );

        return $this->success(new RoleResource($updated));
    }

    /**
     * DELETE /api/v2/staff/roles/{role}
     */
    public function destroy(Request $request, string $role)
    {
        $found = $this->roleService->find($role);

        $this->roleService->delete($found, $request->user());

        return $this->success(null, 'Role deleted successfully.');
    }

    /**
     * POST /api/v2/staff/roles/{role}/assign
     */
    public function assign(AssignRoleRequest $request, string $role)
    {
        $found = $this->roleService->find($role);
        $user = User::findOrFail($request->user_id);

        $this->roleService->assignToUser($found, $user, $request->user());

        return $this->success(null, 'Role assigned successfully.');
    }

    /**
     * POST /api/v2/staff/roles/{role}/unassign
     */
    public function unassign(AssignRoleRequest $request, string $role)
    {
        $found = $this->roleService->find($role);
        $user = User::findOrFail($request->user_id);

        $this->roleService->removeFromUser($found, $user, $request->user());

        return $this->success(null, 'Role removed successfully.');
    }

    /**
     * GET /api/v2/staff/roles/user-permissions?store_id=xxx
     *
     * Get the effective permissions for the authenticated user.
     * Also returns branch scope and accessible store IDs.
     *
     * `store_id` is optional. When omitted, we resolve a default store from:
     *   1. The user's own `store_id` (branch-scoped users), else
     *   2. The first active store in the user's organization (org-scoped users).
     */
    public function userPermissions(Request $request)
    {
        $request->validate(['store_id' => 'nullable|uuid|exists:stores,id']);

        $user    = $request->user();
        $storeId = $request->store_id ?? $this->resolveDefaultStoreId($user);

        if ($storeId === null) {
            return $this->success([
                'permissions'          => [],
                'branch_scope'         => 'branch',
                'accessible_store_ids' => [],
                'branch_roles'         => (object) [],
                'store_id'             => null,
            ]);
        }

        $permissions        = $this->roleService->getEffectivePermissions($user, $storeId);
        $branchScope        = $this->roleService->getUserBranchScope($user, $storeId);
        $accessibleStoreIds = $this->roleService->getAccessibleStoreIds($user, $storeId);
        $branchRoles        = $this->roleService->getUserBranchRoles($user, $storeId);

        return $this->success([
            'permissions'          => $permissions,
            'branch_scope'         => $branchScope,
            'accessible_store_ids' => $accessibleStoreIds,
            'branch_roles'         => (object) $branchRoles,
            'store_id'             => $storeId,
        ]);
    }

    /**
     * Resolve a default store id for the user when none is supplied.
     * Returns null when the user has no store and no organization stores.
     */
    private function resolveDefaultStoreId(User $user): ?string
    {
        if (! empty($user->store_id)) {
            return $user->store_id;
        }
        if (! empty($user->organization_id)) {
            return \App\Domain\Core\Models\Store::query()
                ->where('organization_id', $user->organization_id)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->value('id');
        }
        return null;
    }

    /**
     * GET /api/v2/staff/roles/audit-log
     *
     * Return a paginated list of role-permission audit events for the current store.
     */
    public function auditLog(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;

        $query = RoleAuditLog::with(['user:id,name,email', 'role:id,name,display_name'])
            ->where('store_id', $storeId)
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->integer('per_page', 25);
        $logs = $query->paginate($perPage);

        $items = $logs->map(fn ($log) => [
            'id'          => $log->id,
            'action'      => $log->action?->value ?? $log->action,
            'role_id'     => $log->role_id,
            'role_name'   => $log->role?->display_name ?? $log->role?->name,
            'user_id'     => $log->user_id,
            'user_name'   => $log->user?->name,
            'user_email'  => $log->user?->email,
            'details'     => $log->details,
            'created_at'  => $log->created_at,
        ]);

        return $this->successPaginated($items, $logs);
    }
}
