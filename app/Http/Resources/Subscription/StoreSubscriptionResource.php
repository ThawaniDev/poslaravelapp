<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Compute grace_period_ends_at: when status is 'grace', the subscription expires
        // at current_period_end + plan's grace_period_days
        $gracePeriodEndsAt = null;
        if ($this->status?->value === 'grace' && $this->current_period_end) {
            $graceDays = $this->subscriptionPlan?->grace_period_days ?? 7;
            $gracePeriodEndsAt = $this->current_period_end->copy()->addDays($graceDays)->toIso8601String();
        }

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'status' => $this->status,
            'billing_cycle' => $this->billing_cycle,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'grace_period_ends_at' => $gracePeriodEndsAt,
            'payment_method' => $this->payment_method,
            'is_softpos_free' => (bool) $this->is_softpos_free,
            'softpos_transaction_count' => (int) ($this->softpos_transaction_count ?? 0),
            'softpos_sales_total' => (float) ($this->softpos_sales_total ?? 0),

            'plan' => $this->whenLoaded('subscriptionPlan', fn () =>
                new SubscriptionPlanResource($this->subscriptionPlan)
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
