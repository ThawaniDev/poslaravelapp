<?php

namespace App\Http\Middleware;

use App\Domain\StaffManagement\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('permission:orders.view')
     *                  ->middleware('permission:orders.view,orders.manage')  // any of these
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthenticated', [], 'en'),
            ], 401);
        }

        $storeId = $user->store_id;

        if (!$storeId) {
            return response()->json([
                'success' => false,
                'message' => 'No store context found.',
            ], 403);
        }

        // Owner role bypasses all permission checks
        if ($this->isOwner($user, $storeId)) {
            return $next($request);
        }

        $effectivePermissions = $this->roleService->getEffectivePermissions($user, $storeId);

        // Check if user has ANY of the required permissions
        foreach ($permissions as $permission) {
            if (in_array($permission, $effectivePermissions, true)) {
                return $next($request);
            }
        }

        $permissionNames = implode(', ', $permissions);

        return response()->json([
            'success' => false,
            'message' => "You do not have permission to perform this action. Required: {$permissionNames}",
            'required_permissions' => $permissions,
        ], 403);
    }

    private function isOwner($user, string $storeId): bool
    {
        return \DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('roles.store_id', $storeId)
            ->where('roles.name', 'owner')
            ->exists();
    }
}
