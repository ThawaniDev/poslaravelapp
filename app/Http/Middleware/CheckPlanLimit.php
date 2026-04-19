<?php

namespace App\Http\Middleware;

use App\Domain\Subscription\Services\PlanEnforcementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if the organization is within plan limits.
 *
 * Usage in routes: ->middleware('plan.limit:limit_key')
 */
class CheckPlanLimit
{
    public function __construct(private PlanEnforcementService $enforcement)
    {
    }

    public function handle(Request $request, Closure $next, string $limitKey): Response
    {
        $organizationId = $this->resolveOrganizationId($request);

        if (! $organizationId) {
            return response()->json([
                'success' => false,
                'message' => __('subscription.organization_required'),
                'error_code' => 'no_organization',
            ], 403);
        }

        if (! $this->enforcement->canPerformAction($organizationId, $limitKey)) {
            $remaining = $this->enforcement->getRemainingQuota($organizationId, $limitKey);
            $effectiveLimit = $this->enforcement->getEffectiveLimit($organizationId, $limitKey);

            return response()->json([
                'success' => false,
                'message' => __('subscription.plan_limit_exceeded'),
                'error_code' => 'limit_exceeded',
                'limit_key' => $limitKey,
                'current_limit' => $effectiveLimit,
                'remaining' => $remaining,
                'upgrade_required' => true,
            ], 403);
        }

        return $next($request);
    }

    private function resolveOrganizationId(Request $request): ?string
    {
        $user = $request->user();

        return $user?->organization_id;
    }
}
