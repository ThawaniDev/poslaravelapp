<?php

namespace App\Http\Middleware;

use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check that the organization has an active subscription.
 *
 * Usage in routes: ->middleware('plan.active')
 */
class CheckActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $this->resolveOrganizationId($request);

        if (! $organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Organization context required.',
                'error_code' => 'no_organization',
            ], 403);
        }

        $subscription = StoreSubscription::where('organization_id', $organizationId)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->first();

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found. Please subscribe to a plan to access this feature.',
                'message_ar' => 'لا يوجد اشتراك نشط. يرجى الاشتراك في خطة للوصول إلى هذه الميزة.',
                'error_code' => 'no_subscription',
                'subscription_required' => true,
            ], 403);
        }

        // Inject subscription into request for downstream use
        $request->attributes->set('subscription', $subscription);
        $request->attributes->set('subscription_status', $subscription->status->value);

        return $next($request);
    }

    private function resolveOrganizationId(Request $request): ?string
    {
        $user = $request->user();

        return $user?->organization_id;
    }
}
