<?php

namespace App\Http\Controllers\Api\Content;

use App\Domain\ContentOnboarding\Models\PricingPageContent;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Content\PricingPageContentResource;
use Illuminate\Http\JsonResponse;

class PricingPageController extends BaseApiController
{
    /**
     * GET /v2/pricing — List all published pricing page content with plan data.
     */
    public function index(): JsonResponse
    {
        $items = PricingPageContent::with('subscriptionPlan')
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success(PricingPageContentResource::collection($items));
    }

    /**
     * GET /v2/pricing/{planSlug} — Get pricing content for a specific plan by slug.
     */
    public function showBySlug(string $planSlug): JsonResponse
    {
        $plan = SubscriptionPlan::where('slug', $planSlug)->first();

        if (! $plan) {
            return $this->notFound("No plan found with slug '{$planSlug}'.");
        }

        $content = PricingPageContent::with('subscriptionPlan')
            ->where('subscription_plan_id', $plan->id)
            ->where('is_published', true)
            ->first();

        if (! $content) {
            return $this->notFound("No pricing page content found for plan '{$planSlug}'.");
        }

        return $this->success(new PricingPageContentResource($content));
    }

    /**
     * GET /v2/pricing/plan/{planId} — Get pricing content for a specific plan by UUID.
     */
    public function showByPlan(string $planId): JsonResponse
    {
        $content = PricingPageContent::with('subscriptionPlan')
            ->where('subscription_plan_id', $planId)
            ->where('is_published', true)
            ->first();

        if (! $content) {
            return $this->notFound("No pricing page content found for plan ID '{$planId}'.");
        }

        return $this->success(new PricingPageContentResource($content));
    }
}
