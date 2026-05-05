<?php

namespace App\Http\Middleware;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Enums\UserRole;
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

        // Admin users (admin-api guard) use the AdminUser permission system,
        // which is role-based without requiring a store context.
        if ($user instanceof AdminUser) {
            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            foreach ($permissions as $permission) {
                if ($user->hasPermission($permission)) {
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

        // Store staff users — permission check requires a store context.
        $storeId = $this->resolveStoreId($request, $user);

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

    /**
     * Resolve effective store id for permission checks.
     * Order: user.store_id → X-Store-Id header → ?store_id query → first active
     * store in user's organization.
     */
    private function resolveStoreId(Request $request, $user): ?string
    {
        if (!empty($user->store_id)) {
            return $user->store_id;
        }
        $headerStore = $request->header('X-Store-Id');
        if (is_string($headerStore) && $headerStore !== '') {
            return $headerStore;
        }
        $queryStore = $request->query('store_id');
        if (is_string($queryStore) && $queryStore !== '') {
            return $queryStore;
        }
        if (!empty($user->organization_id)) {
            return \App\Domain\Core\Models\Store::query()
                ->where('organization_id', $user->organization_id)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->value('id');
        }
        return null;
    }

    private function isOwner($user, string $storeId): bool
    {
        // Check the user's role enum field first (covers all owners)
        if ($user->role === UserRole::Owner) {
            return true;
        }

        return \DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('roles.store_id', $storeId)
            ->where('roles.name', 'owner')
            ->exists();
    }
}
