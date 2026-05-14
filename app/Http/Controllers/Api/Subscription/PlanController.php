<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Subscription\CreatePlanRequest;
use App\Http\Requests\Subscription\ListPlansRequest;
use App\Http\Requests\Subscription\UpdatePlanRequest;
use App\Http\Resources\Subscription\PlanComparisonResource;
use App\Http\Resources\Subscription\SubscriptionPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends BaseApiController
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    /**
     * GET /subscription/plans — List available plans.
     * Authenticated users see plans filtered by their organization's business_type.
     * Public (unauthenticated) callers only see plans not flagged as hide_from_public.
     */
    public function index(ListPlansRequest $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', true);

        // Auto-filter by the provider's business type if authenticated
        $businessType = null;
        if ($request->user()?->organization) {
            $businessType = $request->user()->organization->business_type?->value;
        }

        // Authenticated users (e.g. checking plans during upgrade) can see hidden plans.
        // Public / unauthenticated requests (pricing page) must not see hide_from_public plans.
        $publicOnly = $request->user() === null;

        $plans = $this->subscriptionService->listPlans($activeOnly, $businessType, $publicOnly);

        return $this->success(SubscriptionPlanResource::collection($plans));
    }

    /**
     * GET /subscription/plans/{planId} — Get a single plan.
     */
    public function show(string $planId): JsonResponse
    {
        try {
            $plan = $this->subscriptionService->getPlan($planId);

            return $this->success(new SubscriptionPlanResource($plan));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Subscription plan not found.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PlanController@show failed', [
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);
            return $this->notFound('Subscription plan not found.');
        }
    }

    /**
     * GET /subscription/plans/slug/{slug} — Get a plan by slug.
     */
    public function showBySlug(string $slug): JsonResponse
    {
        try {
            $plan = $this->subscriptionService->getPlanBySlug($slug);

            return $this->success(new SubscriptionPlanResource($plan));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound("No plan found with slug '{$slug}'.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PlanController@showBySlug failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);
            return $this->notFound("No plan found with slug '{$slug}'.");
        }
    }

    /**
     * POST /subscription/plans/compare — Compare multiple plans.
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'plan_ids' => ['required', 'array', 'min:2', 'max:5'],
            'plan_ids.*' => ['required', 'uuid', 'exists:subscription_plans,id'],
        ]);

        $comparison = $this->subscriptionService->comparePlans($request->input('plan_ids'));

        return $this->success(new PlanComparisonResource($comparison));
    }

    /**
     * POST /subscription/plans — Create a new plan (admin only).
     */
    public function store(CreatePlanRequest $request): JsonResponse
    {
        try {
            $plan = $this->subscriptionService->createPlan($request->validated());

            return $this->created(new SubscriptionPlanResource($plan), 'Plan created successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to create plan: ' . $e->getMessage(), 422);
        }
    }

    /**
     * PUT /subscription/plans/{planId} — Update a plan (admin only).
     */
    public function update(UpdatePlanRequest $request, string $planId): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            $updated = $this->subscriptionService->updatePlan($plan, $request->validated());

            return $this->success(new SubscriptionPlanResource($updated), 'Plan updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Subscription plan not found.');
        } catch (\Throwable $e) {
            return $this->error('Failed to update plan: ' . $e->getMessage(), 422);
        }
    }

    /**
     * PATCH /subscription/plans/{planId}/toggle — Toggle plan active status.
     */
    public function toggle(string $planId): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            $toggled = $this->subscriptionService->togglePlan($plan);

            $status = $toggled->is_active ? 'activated' : 'deactivated';

            return $this->success(new SubscriptionPlanResource($toggled), "Plan {$status}.");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Subscription plan not found.');
        }
    }

    /**
     * DELETE /subscription/plans/{planId} — Delete a plan (admin only).
     */
    public function destroy(string $planId): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            $this->subscriptionService->deletePlan($plan);

            return $this->success(null, 'Plan deleted successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Subscription plan not found.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }
    }

    /**
     * GET /subscription/add-ons — List available add-ons.
     */
    public function addOns(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', true);
        $addOns = $this->subscriptionService->listAddOns($activeOnly);

        return $this->success($addOns);
    }
}
