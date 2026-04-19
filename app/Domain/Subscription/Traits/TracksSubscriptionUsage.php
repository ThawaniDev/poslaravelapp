<?php

namespace App\Domain\Subscription\Traits;

use App\Domain\Subscription\Services\PlanEnforcementService;

/**
 * Trait for controllers that create/delete plan-limited resources.
 *
 * After creating or deleting a resource, call refreshUsageFor()
 * so that the snapshot stays in sync with live counts.
 */
trait TracksSubscriptionUsage
{
    /**
     * Resolve the organization ID from the current request user.
     * Tries direct organization_id first, then looks it up via store_id.
     */
    protected function resolveOrganizationId(\Illuminate\Http\Request $request): ?string
    {
        $user = $request->user();

        if ($user?->organization_id) {
            return $user->organization_id;
        }

        if ($user?->store_id) {
            return \Illuminate\Support\Facades\DB::table('stores')
                ->where('id', $user->store_id)
                ->value('organization_id');
        }

        return null;
    }

    /**
     * Refresh the usage snapshot for specific limit keys after a mutation.
     *
     * @param string      $organizationId
     * @param string|array $limitKeys  e.g. 'products' or ['products', 'storage_mb']
     */
    protected function refreshUsageFor(string $organizationId, string|array $limitKeys): void
    {
        $enforcement = app(PlanEnforcementService::class);
        $keys = (array) $limitKeys;

        foreach ($keys as $key) {
            $liveCount = $enforcement->getLiveUsageCount($organizationId, $key);
            $enforcement->trackUsage($organizationId, $key, $liveCount);
        }
    }

    /**
     * Check if a limit would be exceeded, and return an error response if so.
     * Returns null if the action is allowed.
     */
    protected function checkLimitOrFail(string $organizationId, string $limitKey): ?\Illuminate\Http\JsonResponse
    {
        $enforcement = app(PlanEnforcementService::class);

        if (! $enforcement->canPerformAction($organizationId, $limitKey)) {
            $effectiveLimit = $enforcement->getEffectiveLimit($organizationId, $limitKey);
            $current = $enforcement->getLiveUsageCount($organizationId, $limitKey);
            $resourceLabel = __("subscription.limit_key_{$limitKey}");

            return response()->json([
                'success' => false,
                'message' => __('subscription.limit_exceeded', ['resource' => $resourceLabel]),
                'error_code' => 'limit_exceeded',
                'limit_key' => $limitKey,
                'current_usage' => $current,
                'current_limit' => $effectiveLimit,
                'upgrade_required' => true,
            ], 403);
        }

        return null;
    }
}
