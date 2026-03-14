<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $billingCycle = $this->billing_cycle;

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'status' => $status instanceof \BackedEnum ? $status->value : $status,
            'billing_cycle' => $billingCycle instanceof \BackedEnum ? $billingCycle->value : $billingCycle,
            'current_period_start' => $this->current_period_start instanceof \DateTimeInterface
                ? $this->current_period_start->toIso8601String()
                : $this->current_period_start,
            'current_period_end' => $this->current_period_end instanceof \DateTimeInterface
                ? $this->current_period_end->toIso8601String()
                : $this->current_period_end,
            'trial_ends_at' => $this->trial_ends_at instanceof \DateTimeInterface
                ? $this->trial_ends_at->toIso8601String()
                : $this->trial_ends_at,
            'cancelled_at' => $this->cancelled_at instanceof \DateTimeInterface
                ? $this->cancelled_at->toIso8601String()
                : $this->cancelled_at,
            'plan' => $this->when(
                $this->relationLoaded('subscriptionPlan'),
                fn () => new SubscriptionPlanResource($this->subscriptionPlan),
            ),
            'invoices' => $this->when(
                $this->relationLoaded('invoices'),
                fn () => InvoiceResource::collection($this->invoices),
            ),
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
            'updated_at' => $this->updated_at instanceof \DateTimeInterface
                ? $this->updated_at->toIso8601String()
                : $this->updated_at,
        ];
    }
}
