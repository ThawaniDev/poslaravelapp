<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Helpers for controllers to apply branch-scope filtering.
 *
 * When a specific store is selected, queries filter by that store_id.
 * When "all stores" is selected (org-scoped, no X-Store-Id header),
 * queries filter across all accessible store IDs.
 */
trait ResolvesBranchScope
{
    /**
     * Apply store filtering to an Eloquent query.
     *
     * Single store → WHERE store_id = ?
     * All stores   → WHERE store_id IN (...)
     */
    protected function storeScope(Builder $query, Request $request, string $column = 'store_id'): Builder
    {
        $resolvedStoreId = $request->attributes->get('resolved_store_id');

        if ($resolvedStoreId !== null) {
            return $query->where($column, $resolvedStoreId);
        }

        $storeIds = $request->attributes->get('resolved_store_ids', []);
        if (!empty($storeIds)) {
            return $query->whereIn($column, $storeIds);
        }

        // No branch.scope middleware ran — resolve manually.
        $user = $request->user();

        if ($user?->store_id) {
            return $query->where($column, $user->store_id);
        }

        // Org-level user (no store_id): scope across all active org stores.
        if ($user?->organization_id) {
            $orgStoreIds = \App\Domain\Core\Models\Store::where('organization_id', $user->organization_id)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
            if (!empty($orgStoreIds)) {
                return $query->whereIn($column, $orgStoreIds);
            }
        }

        // Fully misconfigured user — return nothing safely.
        return $query->whereRaw('1=0');
    }

    /**
     * Get the effective store ID. Returns null when "all stores" is selected.
     */
    protected function resolvedStoreId(Request $request): ?string
    {
        return $request->attributes->get('resolved_store_id');
    }

    /**
     * Get the array of store IDs to query against (always non-empty).
     */
    protected function resolvedStoreIds(Request $request): array
    {
        $fromMiddleware = $request->attributes->get('resolved_store_ids');
        if (!empty($fromMiddleware)) {
            return $fromMiddleware;
        }

        // No branch.scope middleware — fall back manually.
        $user = $request->user();

        // Branch user with an assigned store.
        if ($user?->store_id) {
            return [$user->store_id];
        }

        // Org-level user (no store_id): query all active stores in their org.
        if ($user?->organization_id) {
            $storeIds = \App\Domain\Core\Models\Store::where('organization_id', $user->organization_id)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
            if (!empty($storeIds)) {
                return $storeIds;
            }
        }

        return [];
    }

    /**
     * Check whether the current user can access a given store.
     *
     * Uses accessible_store_ids from the BranchScope middleware.
     * Falls back to comparing against the user's own store_id.
     */
    protected function canAccessStore(Request $request, ?string $storeId): bool
    {
        if ($storeId === null) {
            return false;
        }

        $accessible = $request->attributes->get('accessible_store_ids');

        if (is_array($accessible) && !empty($accessible)) {
            return in_array($storeId, $accessible, true);
        }

        return $storeId === $request->user()?->store_id;
    }
}
