<?php

namespace App\Http\Middleware;

use App\Domain\Subscription\Services\PlanEnforcementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if the organization's plan has a specific feature enabled.
 *
 * Usage in routes: ->middleware('plan.feature:feature_key')
 */
class CheckPlanFeature
{
    public function __construct(private PlanEnforcementService $enforcement)
    {
    }

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $organizationId = $this->resolveOrganizationId($request);

        if (! $organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Organization context required.',
                'error_code' => 'no_organization',
            ], 403);
        }

        if (! $this->enforcement->isFeatureEnabled($organizationId, $featureKey)) {
            return response()->json([
                'success' => false,
                'message' => 'This feature is not available on your current plan. Please upgrade to access this feature.',
                'message_ar' => 'هذه الميزة غير متوفرة في خطتك الحالية. يرجى الترقية للوصول إلى هذه الميزة.',
                'error_code' => 'feature_not_available',
                'feature_key' => $featureKey,
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
