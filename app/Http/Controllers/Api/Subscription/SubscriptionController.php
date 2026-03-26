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
}
