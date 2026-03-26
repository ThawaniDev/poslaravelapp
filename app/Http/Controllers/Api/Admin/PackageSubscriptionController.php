<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Services\BillingService;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\CreateDiscountRequest;
use App\Http\Requests\Admin\CreatePlanAddOnRequest;
use App\Http\Requests\Admin\CreatePlanRequest;
use App\Http\Requests\Admin\UpdateDiscountRequest;
use App\Http\Requests\Admin\UpdatePlanAddOnRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\PlanAddOnResource;
use App\Http\Resources\Admin\StoreSubscriptionResource;
use App\Http\Resources\Admin\SubscriptionDiscountResource;
use App\Http\Resources\Admin\SubscriptionPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageSubscriptionController extends BaseApiController
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly BillingService $billingService,
    ) {}

    // ─── Plans ──────────────────────────────────────────────────

    public function listPlans(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', false);
        $plans = $this->subscriptionService->listPlans($activeOnly);

        return $this->success(
            SubscriptionPlanResource::collection($plans)->resolve(),
            'Plans retrieved',
        );
    }

    public function showPlan(string $planId): JsonResponse
    {
        $plan = $this->subscriptionService->getPlan($planId);

        return $this->success(new SubscriptionPlanResource($plan), 'Plan retrieved');
    }

    public function createPlan(CreatePlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name'], '_');
        }
        $plan = $this->subscriptionService->createPlan($data);

        return $this->created(new SubscriptionPlanResource($plan), 'Plan created');
    }

    public function updatePlan(UpdatePlanRequest $request, string $planId): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $updated = $this->subscriptionService->updatePlan($plan, $request->validated());

        return $this->success(new SubscriptionPlanResource($updated), 'Plan updated');
    }

    public function togglePlan(string $planId): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $toggled = $this->subscriptionService->togglePlan($plan);

        return $this->success(
            new SubscriptionPlanResource($toggled),
            $toggled->is_active ? 'Plan activated' : 'Plan deactivated',
        );
    }

    public function deletePlan(string $planId): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        try {
            $this->subscriptionService->deletePlan($plan);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(null, 'Plan deleted');
    }

    public function comparePlans(Request $request): JsonResponse
    {
        $planIds = $request->input('plan_ids', []);
        $comparison = $this->subscriptionService->comparePlans($planIds);

        return $this->success([
            'plans' => SubscriptionPlanResource::collection($comparison['plans'])->resolve(),
            'features' => $comparison['features'],
            'limits' => $comparison['limits'],
        ], 'Plans comparison');
    }

    // ─── Add-Ons ────────────────────────────────────────────────

    public function listAddOns(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', false);
        $addOns = $this->subscriptionService->listAddOns($activeOnly);

        return $this->success(
            PlanAddOnResource::collection($addOns)->resolve(),
            'Add-ons retrieved',
        );
    }

    public function showAddOn(string $addOnId): JsonResponse
    {
        $addOn = PlanAddOn::findOrFail($addOnId);

        return $this->success(new PlanAddOnResource($addOn), 'Add-on retrieved');
    }

    public function createAddOn(CreatePlanAddOnRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name'], '_');
        }
        $addOn = PlanAddOn::create($data);

        return $this->created(new PlanAddOnResource($addOn), 'Add-on created');
    }

    public function updateAddOn(UpdatePlanAddOnRequest $request, string $addOnId): JsonResponse
    {
        $addOn = PlanAddOn::findOrFail($addOnId);
        $addOn->update($request->validated());

        return $this->success(new PlanAddOnResource($addOn->fresh()), 'Add-on updated');
    }

    public function deleteAddOn(string $addOnId): JsonResponse
    {
        $addOn = PlanAddOn::findOrFail($addOnId);
        $addOn->delete();

        return $this->success(null, 'Add-on deleted');
    }

    // ─── Discounts ──────────────────────────────────────────────

    public function listDiscounts(Request $request): JsonResponse
    {
        $query = SubscriptionDiscount::query()->orderByDesc('created_at');

        if ($request->has('active')) {
            $now = now();
            $query->where('valid_from', '<=', $now)
                  ->where('valid_to', '>=', $now);
        }

        $discounts = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'discounts' => SubscriptionDiscountResource::collection($discounts->items())->resolve(),
            'pagination' => [
                'total' => $discounts->total(),
                'current_page' => $discounts->currentPage(),
                'last_page' => $discounts->lastPage(),
                'per_page' => $discounts->perPage(),
            ],
        ], 'Discounts retrieved');
    }

    public function showDiscount(string $discountId): JsonResponse
    {
        $discount = SubscriptionDiscount::findOrFail($discountId);

        return $this->success(new SubscriptionDiscountResource($discount), 'Discount retrieved');
    }

    public function createDiscount(CreateDiscountRequest $request): JsonResponse
    {
        $discount = SubscriptionDiscount::create($request->validated());

        return $this->created(new SubscriptionDiscountResource($discount), 'Discount created');
    }

    public function updateDiscount(UpdateDiscountRequest $request, string $discountId): JsonResponse
    {
        $discount = SubscriptionDiscount::findOrFail($discountId);
        $discount->update($request->validated());

        return $this->success(new SubscriptionDiscountResource($discount->fresh()), 'Discount updated');
    }

    public function deleteDiscount(string $discountId): JsonResponse
    {
        $discount = SubscriptionDiscount::findOrFail($discountId);
        $discount->delete();

        return $this->success(null, 'Discount deleted');
    }

    // ─── Subscriptions (Admin Overview) ─────────────────────────

    public function listSubscriptions(Request $request): JsonResponse
    {
        $query = StoreSubscription::with(['subscriptionPlan'])
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('plan_id')) {
            $query->where('subscription_plan_id', $request->input('plan_id'));
        }

        if ($request->has('store_id')) {
            $query->where('organization_id', $request->input('store_id'));
        }

        $subscriptions = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'subscriptions' => StoreSubscriptionResource::collection($subscriptions->items())->resolve(),
            'pagination' => [
                'total' => $subscriptions->total(),
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
            ],
        ], 'Subscriptions retrieved');
    }

    public function showSubscription(string $subscriptionId): JsonResponse
    {
        $subscription = StoreSubscription::with([
            'subscriptionPlan.planFeatureToggles',
            'subscriptionPlan.planLimits',
            'invoices',
        ])->findOrFail($subscriptionId);

        return $this->success(new StoreSubscriptionResource($subscription), 'Subscription retrieved');
    }

    // ─── Invoices (Admin) ───────────────────────────────────────

    public function listInvoices(Request $request): JsonResponse
    {
        $query = \App\Domain\ProviderSubscription\Models\Invoice::with(['invoiceLineItems', 'storeSubscription'])
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('store_subscription_id')) {
            $query->where('store_subscription_id', $request->input('store_subscription_id'));
        }

        $invoices = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'invoices' => InvoiceResource::collection($invoices->items())->resolve(),
            'pagination' => [
                'total' => $invoices->total(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
            ],
        ], 'Invoices retrieved');
    }

    public function showInvoice(string $invoiceId): JsonResponse
    {
        $invoice = $this->billingService->getInvoice($invoiceId);

        return $this->success(new InvoiceResource($invoice), 'Invoice retrieved');
    }

    // ─── Revenue Dashboard ──────────────────────────────────────

    public function revenueDashboard(): JsonResponse
    {
        $totalActive = StoreSubscription::where('status', 'active')->count();
        $totalTrial = StoreSubscription::where('status', 'trial')->count();
        $totalGrace = StoreSubscription::where('status', 'grace')->count();
        $totalCancelled = StoreSubscription::where('status', 'cancelled')->count();

        $monthlyRevenue = \App\Domain\ProviderSubscription\Models\Invoice::where('status', 'paid')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('total');

        $pendingInvoices = \App\Domain\ProviderSubscription\Models\Invoice::where('status', 'pending')->count();

        return $this->success([
            'subscriptions' => [
                'active' => $totalActive,
                'trial' => $totalTrial,
                'grace' => $totalGrace,
                'cancelled' => $totalCancelled,
            ],
            'revenue' => [
                'monthly' => (float) $monthlyRevenue,
                'pending_invoices' => $pendingInvoices,
            ],
        ], 'Revenue dashboard');
    }
}
