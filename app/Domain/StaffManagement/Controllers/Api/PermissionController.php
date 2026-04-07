<?php

namespace App\Domain\StaffManagement\Controllers\Api;

use App\Domain\StaffManagement\Resources\PermissionResource;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

class PermissionController extends BaseApiController
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * GET /api/v2/staff/permissions
     *
     * List all permissions (flat list).
     */
    public function index()
    {
        return $this->success(PermissionResource::collection($this->permissionService->all()));
    }

    /**
     * GET /api/v2/staff/permissions/grouped
     *
     * List all permissions grouped by module.
     */
    public function grouped()
    {
        $grouped = $this->permissionService->groupedByModule();

        // Transform grouped data with full details
        $result = [];
        foreach ($grouped as $module => $perms) {
            $result[$module] = collect($perms)->map(fn ($p) => [
                'id'               => $p['id'],
                'name'             => $p['name'],
                'display_name'     => $p['display_name'],
                'display_name_ar'  => $p['display_name_ar'] ?? null,
                'description'      => $p['description'] ?? null,
                'description_ar'   => $p['description_ar'] ?? null,
                'requires_pin'     => $p['requires_pin'],
            ])->values()->toArray();
        }

        return $this->success($result);
    }

    /**
     * GET /api/v2/staff/permissions/modules
     */
    public function modules()
    {
        return $this->success($this->permissionService->modules());
    }

    /**
     * GET /api/v2/staff/permissions/module/{module}
     */
    public function forModule(string $module)
    {
        return $this->success(
            PermissionResource::collection($this->permissionService->forModule($module))
        );
    }

    /**
     * GET /api/v2/staff/permissions/pin-protected
     */
    public function pinProtected()
    {
        return $this->success(
            PermissionResource::collection($this->permissionService->pinProtected())
        );
    }
}
