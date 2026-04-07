<?php

namespace App\Domain\StaffManagement\Controllers\Api;

use App\Domain\StaffManagement\Requests\AssignRoleRequest;
use App\Domain\StaffManagement\Requests\CreateRoleRequest;
use App\Domain\StaffManagement\Requests\UpdateRoleRequest;
use App\Domain\StaffManagement\Resources\RoleResource;
use App\Domain\StaffManagement\Services\RoleService;
use App\Domain\Auth\Models\User;
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
        $storeId = $request->user()->store_id;

        $roles = $this->roleService->listForStore($storeId);

        return $this->success(RoleResource::collection($roles));
    }

    /**
     * GET /api/v2/staff/roles/{role}
     */
    public function show(int $role)
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
    public function update(UpdateRoleRequest $request, int $role)
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
    public function destroy(Request $request, int $role)
    {
        $found = $this->roleService->find($role);

        $this->roleService->delete($found, $request->user());

        return $this->success(null, 'Role deleted successfully.');
    }

    /**
     * POST /api/v2/staff/roles/{role}/assign
     */
    public function assign(AssignRoleRequest $request, int $role)
    {
        $found = $this->roleService->find($role);
        $user = User::findOrFail($request->user_id);

        $this->roleService->assignToUser($found, $user, $request->user());

        return $this->success(null, 'Role assigned successfully.');
    }

    /**
     * POST /api/v2/staff/roles/{role}/unassign
     */
    public function unassign(AssignRoleRequest $request, int $role)
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
     */
    public function userPermissions(Request $request)
    {
        $request->validate(['store_id' => 'required|uuid|exists:stores,id']);

        $user = $request->user();
        $storeId = $request->store_id;

        $permissions = $this->roleService->getEffectivePermissions($user, $storeId);
        $branchScope = $this->roleService->getUserBranchScope($user, $storeId);
        $accessibleStoreIds = $this->roleService->getAccessibleStoreIds($user, $storeId);
        $branchRoles = $this->roleService->getUserBranchRoles($user, $storeId);

        return $this->success([
            'permissions'          => $permissions,
            'branch_scope'         => $branchScope,
            'accessible_store_ids' => $accessibleStoreIds,
            'branch_roles'         => $branchRoles,
        ]);
    }
}
