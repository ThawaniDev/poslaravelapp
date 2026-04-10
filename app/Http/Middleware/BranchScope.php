<?php

namespace App\Http\Middleware;

use App\Domain\StaffManagement\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the branch scope for the authenticated user.
 *
 * Sets request attributes:
 *   - branch_scope: 'organization' | 'branch'
 *   - accessible_store_ids: array of store UUIDs the user can access
 *   - resolved_store_id: the effective store_id for this request
 *
 * Branch-scoped users are restricted to their own store_id.
 * Organization-scoped users can access any store in their org via ?store_id= or X-Store-Id header.
 *
 * Usage in routes: ->middleware('branch.scope')
 */
class BranchScope
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !method_exists($user, 'getAttribute') || !array_key_exists('store_id', $user->getAttributes())) {
            return $next($request);
        }

        $storeId = $user->store_id;
        if (!$storeId) {
            return $next($request);
        }
        $scope = $this->roleService->getUserBranchScope($user, $storeId);
        $accessibleStoreIds = $this->roleService->getAccessibleStoreIds($user, $storeId);

        // Determine the resolved store_id for this request
        $requestedStoreId = $request->input('store_id')
            ?? $request->header('X-Store-Id')
            ?? $storeId;

        // Branch-scoped users can ONLY access their own store
        if ($scope === 'branch') {
            $requestedStoreId = $storeId;
        }

        // Organization-scoped users validate requested store is in their org
        if ($scope === 'organization' && !in_array($requestedStoreId, $accessibleStoreIds)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch.',
            ], 403);
        }

        // Set attributes for downstream controllers/services
        $request->attributes->set('branch_scope', $scope);
        $request->attributes->set('accessible_store_ids', $accessibleStoreIds);
        $request->attributes->set('resolved_store_id', $requestedStoreId);

        return $next($request);
    }
}
