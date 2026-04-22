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

        // Org-level users (no assigned store) — typically owners. They can
        // access every store in their organization, OR work at org-level
        // (resolved_store_id = null) for features that support it (e.g. AI).
        if (!$storeId) {
            $accessibleStoreIds = $user->organization_id
                ? \App\Domain\Core\Models\Store::where('organization_id', $user->organization_id)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray()
                : [];

            $requestedStoreId = $request->input('store_id')
                ?? $request->header('X-Store-Id')
                ?? null;
            if ($requestedStoreId === '' || $requestedStoreId === 'all' || $requestedStoreId === 'null') {
                $requestedStoreId = null;
            }
            if ($requestedStoreId !== null && !in_array($requestedStoreId, $accessibleStoreIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this branch.',
                ], 403);
            }

            $request->attributes->set('branch_scope', 'organization');
            $request->attributes->set('accessible_store_ids', $accessibleStoreIds);
            $request->attributes->set('resolved_store_id', $requestedStoreId);
            $request->attributes->set('resolved_store_ids', $requestedStoreId ? [$requestedStoreId] : $accessibleStoreIds);

            return $next($request);
        }

        $scope = $this->roleService->getUserBranchScope($user, $storeId);
        $accessibleStoreIds = $this->roleService->getAccessibleStoreIds($user, $storeId);

        // Ensure user's primary store is always in their accessible list (defensive fallback).
        if (!in_array($storeId, $accessibleStoreIds, true)) {
            $accessibleStoreIds[] = $storeId;
        }

        // Determine the resolved store_id for this request
        // null/empty/"all" means "all stores" for org-scoped users
        $requestedStoreId = $request->input('store_id')
            ?? $request->header('X-Store-Id')
            ?? null;

        // Treat empty string and the literal "all" sentinel as "all stores"
        if ($requestedStoreId === '' || $requestedStoreId === 'all' || $requestedStoreId === 'null') {
            $requestedStoreId = null;
        }

        // Branch-scoped users can ONLY access their own store
        if ($scope === 'branch') {
            $requestedStoreId = $storeId;
        }

        // Organization-scoped users: validate if a specific store is requested
        if ($scope === 'organization' && $requestedStoreId !== null) {
            if (!in_array($requestedStoreId, $accessibleStoreIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this branch.',
                ], 403);
            }
        }

        // Set attributes for downstream controllers/services
        // resolved_store_id is null when org-scoped user selects "all stores"
        // resolved_store_ids is always non-empty (falls back to user's own store if accessible list is empty)
        $resolvedStoreIds = $requestedStoreId
            ? [$requestedStoreId]
            : (!empty($accessibleStoreIds) ? $accessibleStoreIds : [$storeId]);

        $request->attributes->set('branch_scope', $scope);
        $request->attributes->set('accessible_store_ids', $accessibleStoreIds);
        $request->attributes->set('resolved_store_id', $requestedStoreId);
        $request->attributes->set('resolved_store_ids', $resolvedStoreIds);

        return $next($request);
    }
}
