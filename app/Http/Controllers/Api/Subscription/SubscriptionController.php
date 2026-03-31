<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Domain\ProviderSubscription\Services\BillingService;
use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Subscription\Services\PlanEnforcementService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Subscription\ChangePlanRequest;
use App\Http\Requests\Subscription\SubscribeRequest;
use App\Http\Resources\Subscription\StoreSubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly PlanEnforcementService $enforcementService,
    ) {}

    /**
     * GET /subscription/current — Get current subscription.
     */
    public function current(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        $subscription = $this->billingService->getCurrentSubscription($organizationId);

        if (! $subscription) {
            return $this->success(null, 'No active subscription.');
        }

        return $this->success(new StoreSubscriptionResource($subscription));
    }

    /**
     * POST /subscription/subscribe — Subscribe to a plan.
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        try {
            $billingCycle = $request->has('billing_cycle')
                ? BillingCycle::from($request->input('billing_cycle'))
                : BillingCycle::Monthly;

            $subscription = $this->billingService->subscribe(
                organizationId: $organizationId,
                planId: $request->input('plan_id'),
                billingCycle: $billingCycle,
                paymentMethod: $request->input('payment_method'),
            );

            return $this->created(new StoreSubscriptionResource($subscription), 'Subscribed successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Selected plan not found.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }
    }

    /**
     * PUT /subscription/change-plan — Change to a different plan.
     */
    public function changePlan(ChangePlanRequest $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        try {
            $billingCycle = $request->has('billing_cycle')
                ? BillingCycle::from($request->input('billing_cycle'))
                : BillingCycle::Monthly;

            $subscription = $this->billingService->changePlan(
                organizationId: $organizationId,
                newPlanId: $request->input('plan_id'),
                billingCycle: $billingCycle,
            );

            return $this->success(new StoreSubscriptionResource($subscription), 'Plan changed successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Active subscription or target plan not found.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }
    }

    /**
     * POST /subscription/cancel — Cancel subscription.
     */
    public function cancel(CancelSubscriptionRequest $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        try {
            $subscription = $this->billingService->cancelSubscription(
                organizationId: $organizationId,
                reason: $request->input('reason'),
            );

            return $this->success(new StoreSubscriptionResource($subscription), 'Subscription cancelled.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('No active subscription found to cancel.');
        }
    }

    /**
     * POST /subscription/resume — Resume a cancelled subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        try {
            $subscription = $this->billingService->resumeSubscription($organizationId);

            return $this->success(new StoreSubscriptionResource($subscription), 'Subscription resumed.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('No cancelled subscription found to resume.');
        }
    }

    /**
     * GET /subscription/usage — Get plan usage summary.
     */
    public function usage(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        $summary = $this->enforcementService->getUsageSummary($organizationId);

        return $this->success($summary);
    }

    /**
     * GET /subscription/check-feature/{featureKey} — Check if a feature is enabled.
     */
    public function checkFeature(Request $request, string $featureKey): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        $enabled = $this->enforcementService->isFeatureEnabled($organizationId, $featureKey);

        return $this->success([
            'feature_key' => $featureKey,
            'is_enabled' => $enabled,
        ]);
    }

    /**
     * GET /subscription/check-limit/{limitKey} — Check remaining quota.
     */
    public function checkLimit(Request $request, string $limitKey): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        $remaining = $this->enforcementService->getRemainingQuota($organizationId, $limitKey);

        return $this->success([
            'limit_key' => $limitKey,
            'remaining' => $remaining,
            'can_perform' => $remaining === null || $remaining > 0,
        ]);
    }

    /**
     * GET /subscription/sync/entitlements — Get full entitlement snapshot for offline cache.
     */
    public function syncEntitlements(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        $subscription = $this->billingService->getCurrentSubscription($organizationId);

        if (! $subscription) {
            return $this->success([
                'has_subscription' => false,
                'plan_code' => null,
                'plan_name' => null,
                'plan_name_ar' => null,
                'status' => null,
                'features' => [],
                'limits' => [],
                'expires_at' => null,
                'grace_period_ends_at' => null,
                'synced_at' => now()->toIso8601String(),
            ]);
        }

        $plan = $subscription->subscriptionPlan;
        $features = [];
        $limits = [];

        if ($plan) {
            foreach ($plan->planFeatureToggles as $toggle) {
                $features[$toggle->feature_key] = $toggle->is_enabled ?? false;
            }
            foreach ($plan->planLimits as $limit) {
                $effectiveLimit = $this->enforcementService->getEffectiveLimit($organizationId, $limit->limit_key);
                $currentUsage = $this->enforcementService->getCurrentUsage($organizationId, $limit->limit_key);
                $limits[$limit->limit_key] = [
                    'limit' => $effectiveLimit,
                    'current' => $currentUsage,
                ];
            }
        }

        $gracePeriodEndsAt = null;
        if ($subscription->status === \App\Domain\Subscription\Enums\SubscriptionStatus::Grace->value
            || (is_string($subscription->status) && $subscription->status === 'grace')
        ) {
            $gracePeriodEndsAt = $subscription->current_period_end?->toIso8601String();
        }

        return $this->success([
            'has_subscription' => true,
            'plan_code' => $plan?->slug,
            'plan_name' => $plan?->name,
            'plan_name_ar' => $plan?->name_ar,
            'status' => is_string($subscription->status) ? $subscription->status : $subscription->status->value,
            'billing_cycle' => is_string($subscription->billing_cycle)
                ? $subscription->billing_cycle
                : $subscription->billing_cycle?->value,
            'features' => $features,
            'limits' => $limits,
            'expires_at' => $subscription->current_period_end?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'grace_period_ends_at' => $gracePeriodEndsAt,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /subscription/store-add-ons — List active add-ons for current store.
     */
    public function storeAddOns(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->store_id;

        if (! $storeId) {
            return $this->error('No store assigned to this user.', 404);
        }

        $addOns = \App\Domain\ProviderSubscription\Models\StoreAddOn::where('store_id', $storeId)
            ->with('planAddOn')
            ->get()
            ->map(function ($storeAddOn) {
                $addOn = $storeAddOn->planAddOn;

                return [
                    'store_id' => $storeAddOn->store_id,
                    'plan_add_on_id' => $storeAddOn->plan_add_on_id,
                    'is_active' => $storeAddOn->is_active,
                    'activated_at' => $storeAddOn->activated_at,
                    'add_on' => $addOn ? [
                        'id' => $addOn->id,
                        'name' => $addOn->name,
                        'name_ar' => $addOn->name_ar,
                        'slug' => $addOn->slug,
                        'monthly_price' => $addOn->monthly_price,
                        'description' => $addOn->description,
                        'is_active' => $addOn->is_active,
                    ] : null,
                ];
            });

        return $this->success($addOns);
    }
}
