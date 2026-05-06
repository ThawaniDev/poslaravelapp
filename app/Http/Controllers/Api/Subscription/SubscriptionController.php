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
            return $this->error(__('subscription.no_organization'), 404);
        }

        $subscription = $this->billingService->getCurrentSubscription($organizationId);

        if (! $subscription) {
            return $this->success(null, __('subscription.no_active_subscription'));
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
            return $this->error(__('subscription.no_organization'), 404);
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
                discountCode: $request->input('discount_code'),
            );

            return $this->created(new StoreSubscriptionResource($subscription), __('subscription.subscribed_successfully'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound(__('subscription.plan_not_found'));
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
            return $this->error(__('subscription.no_organization'), 404);
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

            return $this->success(new StoreSubscriptionResource($subscription), __('subscription.plan_changed'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound(__('subscription.plan_or_subscription_not_found'));
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
            return $this->error(__('subscription.no_organization'), 404);
        }

        try {
            $subscription = $this->billingService->cancelSubscription(
                organizationId: $organizationId,
                reason: $request->input('reason'),
                reasonCategory: $request->input('reason_category'),
            );

            return $this->success(new StoreSubscriptionResource($subscription), __('subscription.subscription_cancelled'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound(__('subscription.no_active_to_cancel'));
        }
    }

    /**
     * POST /subscription/resume — Resume a cancelled subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error(__('subscription.no_organization'), 404);
        }

        try {
            $subscription = $this->billingService->resumeSubscription($organizationId);

            return $this->success(new StoreSubscriptionResource($subscription), __('subscription.subscription_resumed'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound(__('subscription.no_cancelled_to_resume'));
        }
    }

    /**
     * GET /subscription/usage — Get plan usage summary.
     */
    public function usage(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error(__('subscription.no_organization'), 404);
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
            return $this->error(__('subscription.no_organization'), 404);
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
            return $this->error(__('subscription.no_organization'), 404);
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
            return $this->error(__('subscription.no_organization'), 404);
        }

        $payload = $this->enforcementService->buildEntitlementsPayload($organizationId);
        $payload['synced_at'] = now()->toIso8601String();
        $payload['feature_route_mapping'] = PlanEnforcementService::featureRouteMapping();

        return $this->success($payload);
    }

    /**
     * GET /subscription/softpos/info — Get SoftPOS threshold info.
     */
    public function softPosInfo(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error(__('subscription.no_organization'), 404);
        }

        $softPosService = app(\App\Domain\ProviderSubscription\Services\SoftPosService::class);
        $info = $softPosService->getThresholdInfo($organizationId);

        if (! $info) {
            return $this->success([
                'is_eligible' => false,
                'message' => __('subscription.softpos_not_available'),
            ]);
        }

        return $this->success($info);
    }

    /**
     * GET /subscription/softpos/statistics — Get SoftPOS transaction statistics.
     */
    public function softPosStatistics(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error(__('subscription.no_organization'), 404);
        }

        $softPosService = app(\App\Domain\ProviderSubscription\Services\SoftPosService::class);
        $stats = $softPosService->getStatistics($organizationId);

        return $this->success($stats);
    }

    /**
     * POST /subscription/softpos/record — Record a SoftPOS transaction.
     */
    public function recordSoftPosTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.001',
            'store_id' => 'nullable|uuid|exists:stores,id',
            'order_id' => 'nullable|uuid',
            'transaction_ref' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:50',
            'terminal_id' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error(__('subscription.no_organization'), 404);
        }

        // Verify store belongs to user's organization
        if ($request->filled('store_id')) {
            $store = \App\Domain\Core\Models\Store::where('id', $request->input('store_id'))
                ->where('organization_id', $organizationId)
                ->first();
            if (! $store) {
                return $this->error(__('subscription.store_not_in_organization'), 403);
            }
        }

        $softPosService = app(\App\Domain\ProviderSubscription\Services\SoftPosService::class);
        $transaction = $softPosService->recordTransaction(
            organizationId: $organizationId,
            amount: (float) $request->input('amount'),
            storeId: $request->input('store_id'),
            orderId: $request->input('order_id'),
            transactionRef: $request->input('transaction_ref'),
            paymentMethod: $request->input('payment_method'),
            terminalId: $request->input('terminal_id'),
            metadata: $request->input('metadata', []),
        );

        // Return updated threshold info
        $thresholdInfo = $softPosService->getThresholdInfo($organizationId);

        return $this->created([
            'transaction_id' => $transaction->id,
            'threshold_info' => $thresholdInfo,
        ], __('subscription.softpos_transaction_recorded'));
    }

    /**
     * GET /subscription/softpos/transactions — Get SoftPOS transaction history.
     */
    public function softPosTransactions(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error(__('subscription.no_organization'), 404);
        }

        $softPosService = app(\App\Domain\ProviderSubscription\Services\SoftPosService::class);
        $transactions = $softPosService->getTransactionHistory(
            organizationId: $organizationId,
            perPage: $request->integer('per_page', 25),
            startDate: $request->input('start_date'),
            endDate: $request->input('end_date'),
        );

        return $this->success($transactions);
    }

    /**
     * GET /subscription/features — Get all feature toggles for the current plan.
     */
    public function allFeatures(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error(__('subscription.no_organization'), 404);
        }

        $features = $this->enforcementService->getAllFeatureToggles($organizationId);

        return $this->success($features);
    }

    /**
     * GET /subscription/feature-route-mapping — Get the feature-to-route mapping for sidebar gating.
     */
    public function featureRouteMapping(): JsonResponse
    {
        return $this->success(PlanEnforcementService::featureRouteMapping());
    }

    /**
     * GET /subscription/store-add-ons — List active add-ons for current store.
     */
    public function storeAddOns(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->store_id;

        if (! $storeId) {
            return $this->error(__('subscription.no_store'), 404);
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

    /**
     * POST /subscription/store-add-ons/{addOnId}/activate — Activate an add-on for the current store.
     */
    public function activateAddOn(Request $request, string $addOnId): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->store_id;

        if (! $storeId) {
            return $this->error(__('subscription.no_store'), 404);
        }

        // Verify the add-on exists and is active
        $planAddOn = \App\Domain\Subscription\Models\PlanAddOn::where('id', $addOnId)
            ->where('is_active', true)
            ->first();

        if (! $planAddOn) {
            return $this->error(__('subscription.addon_not_found'), 404);
        }

        // Check if already active
        $existing = \App\Domain\ProviderSubscription\Models\StoreAddOn::where('store_id', $storeId)
            ->where('plan_add_on_id', $addOnId)
            ->first();

        if ($existing && $existing->is_active) {
            return $this->error(__('subscription.addon_already_active'), 422);
        }

        if ($existing) {
            // Reactivate — use direct query because StoreAddOn has composite PK
            \Illuminate\Support\Facades\DB::table('store_add_ons')
                ->where('store_id', $storeId)
                ->where('plan_add_on_id', $addOnId)
                ->update([
                    'is_active' => true,
                    'activated_at' => now(),
                    'deactivated_at' => null,
                ]);
            $storeAddOn = \App\Domain\ProviderSubscription\Models\StoreAddOn::where('store_id', $storeId)
                ->where('plan_add_on_id', $addOnId)
                ->first();
        } else {
            $storeAddOn = \App\Domain\ProviderSubscription\Models\StoreAddOn::create([
                'store_id' => $storeId,
                'plan_add_on_id' => $addOnId,
                'is_active' => true,
                'activated_at' => now(),
            ]);
        }

        return $this->created([
            'store_id' => $storeAddOn->store_id,
            'plan_add_on_id' => $storeAddOn->plan_add_on_id,
            'is_active' => $storeAddOn->is_active,
            'activated_at' => $storeAddOn->activated_at,
            'add_on' => [
                'id' => $planAddOn->id,
                'name' => $planAddOn->name,
                'name_ar' => $planAddOn->name_ar,
                'slug' => $planAddOn->slug,
                'monthly_price' => $planAddOn->monthly_price,
                'description' => $planAddOn->description,
                'is_active' => $planAddOn->is_active,
            ],
        ], __('subscription.addon_activated'));
    }

    /**
     * DELETE /subscription/store-add-ons/{addOnId} — Deactivate (remove) an add-on from the current store.
     */
    public function removeAddOn(Request $request, string $addOnId): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->store_id;

        if (! $storeId) {
            return $this->error(__('subscription.no_store'), 404);
        }

        $storeAddOn = \App\Domain\ProviderSubscription\Models\StoreAddOn::where('store_id', $storeId)
            ->where('plan_add_on_id', $addOnId)
            ->first();

        if (! $storeAddOn) {
            return $this->error(__('subscription.addon_not_found'), 404);
        }

        if (! $storeAddOn->is_active) {
            return $this->error(__('subscription.addon_already_deactivated'), 422);
        }

        // Use direct query because StoreAddOn has composite PK
        \Illuminate\Support\Facades\DB::table('store_add_ons')
            ->where('store_id', $storeId)
            ->where('plan_add_on_id', $addOnId)
            ->update([
                'is_active' => false,
                'deactivated_at' => now(),
            ]);

        return $this->success(null, __('subscription.addon_removed'));
    }

    /**
     * POST /subscription/validate-discount — Validate a discount code and return discounted price.
     */
    public function validateDiscount(Request $request): JsonResponse
    {
        $request->validate([
            'code'     => ['required', 'string', 'max:100'],
            'plan_id'  => ['required', 'uuid'],
            'billing_cycle' => ['sometimes', 'in:monthly,yearly'],
        ]);

        $code     = strtoupper(trim($request->input('code')));
        $planId   = $request->input('plan_id');
        $cycle    = $request->input('billing_cycle', 'monthly');

        $plan = \App\Domain\Subscription\Models\SubscriptionPlan::find($planId);

        if (! $plan || ! $plan->is_active) {
            return $this->notFound(__('subscription.plan_not_found'));
        }

        $discount = \App\Domain\Subscription\Models\SubscriptionDiscount::where('code', $code)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->first();

        if (! $discount) {
            return $this->error(__('subscription.discount_invalid'), 422);
        }

        // Check max uses
        if ($discount->max_uses !== null && $discount->times_used >= $discount->max_uses) {
            return $this->error(__('subscription.discount_expired'), 422);
        }

        // Check plan applicability
        if (! empty($discount->applicable_plan_ids) && ! in_array($planId, $discount->applicable_plan_ids)) {
            return $this->error(__('subscription.discount_not_applicable'), 422);
        }

        $originalPrice = $cycle === 'yearly'
            ? (float) ($plan->annual_price ?? ($plan->monthly_price * 12 * 0.8))
            : (float) $plan->monthly_price;

        /** @var \App\Domain\Promotion\Enums\DiscountType $type */
        $discountType  = $discount->type;
        $discountValue = (float) $discount->value;

        $discountAmount = match ($discountType?->value ?? $discountType) {
            'percentage' => round($originalPrice * $discountValue / 100, 2),
            'fixed'      => min($discountValue, $originalPrice),
            default      => 0,
        };

        $finalPrice = max(0, round($originalPrice - $discountAmount, 2));

        return $this->success([
            'code'             => $discount->code,
            'type'             => $discountType?->value ?? $discountType,
            'value'            => $discountValue,
            'original_price'   => $originalPrice,
            'discount_amount'  => $discountAmount,
            'final_price'      => $finalPrice,
            'currency'         => 'SAR',
            'billing_cycle'    => $cycle,
            'valid_to'         => $discount->valid_to?->toIso8601String(),
        ], __('subscription.discount_valid'));
    }
}
