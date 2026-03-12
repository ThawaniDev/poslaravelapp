<?php

namespace App\Domain\ProviderSubscription\Services;

use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\InvoiceLineItem;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Models\SubscriptionCredit;
use App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot;
use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    // ─── Subscription Queries ────────────────────────────────────

    /**
     * Get the current subscription for a store.
     */
    public function getCurrentSubscription(string $storeId): ?StoreSubscription
    {
        return StoreSubscription::with(['subscriptionPlan.planFeatureToggles', 'subscriptionPlan.planLimits'])
            ->where('store_id', $storeId)
            ->first();
    }

    /**
     * Get invoice history for a store.
     */
    public function getInvoices(string $storeId, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Invoice::with(['invoiceLineItems'])
            ->whereHas('storeSubscription', fn ($q) => $q->where('store_id', $storeId))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get a single invoice.
     */
    public function getInvoice(string $invoiceId): Invoice
    {
        return Invoice::with(['invoiceLineItems', 'storeSubscription.subscriptionPlan'])
            ->findOrFail($invoiceId);
    }

    // ─── Subscription Lifecycle ──────────────────────────────────

    /**
     * Subscribe a store to a plan.
     *
     * @throws \RuntimeException if store already has active subscription
     */
    public function subscribe(
        string $storeId,
        string $planId,
        BillingCycle $billingCycle = BillingCycle::Monthly,
        ?string $paymentMethod = null,
    ): StoreSubscription {
        return DB::transaction(function () use ($storeId, $planId, $billingCycle, $paymentMethod) {
            // Check for existing active subscription
            $existing = StoreSubscription::where('store_id', $storeId)
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trial->value,
                    SubscriptionStatus::Grace->value,
                ])
                ->first();

            if ($existing) {
                throw new \RuntimeException(
                    'Store already has an active subscription. Use changePlan() to switch plans.'
                );
            }

            $plan = SubscriptionPlan::findOrFail($planId);

            if (! $plan->is_active) {
                throw new \RuntimeException("Plan '{$plan->name}' is not currently available.");
            }

            $now = now();
            $hasTrial = $plan->trial_days && $plan->trial_days > 0;

            $subscription = StoreSubscription::create([
                'store_id' => $storeId,
                'subscription_plan_id' => $plan->id,
                'status' => $hasTrial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
                'billing_cycle' => $billingCycle,
                'current_period_start' => $now,
                'current_period_end' => $hasTrial
                    ? $now->copy()->addDays($plan->trial_days)
                    : ($billingCycle === BillingCycle::Yearly ? $now->copy()->addYear() : $now->copy()->addMonth()),
                'trial_ends_at' => $hasTrial ? $now->copy()->addDays($plan->trial_days) : null,
                'payment_method' => $paymentMethod,
            ]);

            // Generate first invoice (skip for trial)
            if (! $hasTrial) {
                $this->generateInvoice($subscription);
            }

            return $subscription->load(['subscriptionPlan.planFeatureToggles', 'subscriptionPlan.planLimits']);
        });
    }

    /**
     * Change to a different plan (upgrade or downgrade).
     */
    public function changePlan(
        string $storeId,
        string $newPlanId,
        BillingCycle $billingCycle = BillingCycle::Monthly,
    ): StoreSubscription {
        return DB::transaction(function () use ($storeId, $newPlanId, $billingCycle) {
            $subscription = StoreSubscription::where('store_id', $storeId)
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trial->value,
                    SubscriptionStatus::Grace->value,
                ])
                ->firstOrFail();

            $newPlan = SubscriptionPlan::where('id', $newPlanId)
                ->where('is_active', true)
                ->firstOrFail();

            if ($subscription->subscription_plan_id === $newPlanId) {
                throw new \RuntimeException('Store is already on this plan.');
            }

            $now = now();
            $subscription->update([
                'subscription_plan_id' => $newPlan->id,
                'billing_cycle' => $billingCycle,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $now,
                'current_period_end' => $billingCycle === BillingCycle::Yearly
                    ? $now->copy()->addYear()
                    : $now->copy()->addMonth(),
                'trial_ends_at' => null,
            ]);

            // Generate prorated invoice for plan change
            $this->generateInvoice($subscription, 'Plan change to ' . $newPlan->name);

            return $subscription->fresh(['subscriptionPlan.planFeatureToggles', 'subscriptionPlan.planLimits']);
        });
    }

    /**
     * Cancel a subscription (enters grace period if applicable).
     */
    public function cancelSubscription(string $storeId, ?string $reason = null): StoreSubscription
    {
        $subscription = StoreSubscription::where('store_id', $storeId)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->firstOrFail();

        $plan = $subscription->subscriptionPlan;
        $now = now();

        // Apply grace period if configured
        if ($plan->grace_period_days && $plan->grace_period_days > 0) {
            $subscription->update([
                'status' => SubscriptionStatus::Grace,
                'cancelled_at' => $now,
                'current_period_end' => $now->copy()->addDays($plan->grace_period_days),
            ]);
        } else {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => $now,
            ]);
        }

        return $subscription->fresh(['subscriptionPlan']);
    }

    /**
     * Resume a cancelled/grace subscription.
     */
    public function resumeSubscription(string $storeId): StoreSubscription
    {
        $subscription = StoreSubscription::where('store_id', $storeId)
            ->whereIn('status', [
                SubscriptionStatus::Cancelled->value,
                SubscriptionStatus::Grace->value,
            ])
            ->firstOrFail();

        $now = now();
        $billingCycle = $subscription->billing_cycle ?? BillingCycle::Monthly;

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
            'current_period_start' => $now,
            'current_period_end' => $billingCycle === BillingCycle::Yearly
                ? $now->copy()->addYear()
                : $now->copy()->addMonth(),
        ]);

        return $subscription->fresh(['subscriptionPlan.planFeatureToggles', 'subscriptionPlan.planLimits']);
    }

    // ─── Invoice Generation ──────────────────────────────────────

    /**
     * Generate an invoice for a subscription.
     */
    public function generateInvoice(StoreSubscription $subscription, ?string $description = null): Invoice
    {
        $plan = $subscription->subscriptionPlan;
        $billingCycle = $subscription->billing_cycle ?? BillingCycle::Monthly;

        $amount = $billingCycle === BillingCycle::Yearly
            ? (float) $plan->annual_price
            : (float) $plan->monthly_price;

        $taxRate = 0.15; // 15% VAT
        $tax = round($amount * $taxRate, 2);
        $total = round($amount + $tax, 2);

        $invoice = Invoice::create([
            'store_subscription_id' => $subscription->id,
            'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
            'amount' => $amount,
            'tax' => $tax,
            'total' => $total,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => $description ?? "{$plan->name} — {$billingCycle->value} subscription",
            'quantity' => 1,
            'unit_price' => $amount,
            'total' => $amount,
        ]);

        return $invoice->load('invoiceLineItems');
    }

    // ─── Credits ─────────────────────────────────────────────────

    /**
     * Apply a credit to a subscription.
     */
    public function applyCredit(
        string $subscriptionId,
        float $amount,
        string $reason,
        string $adminUserId,
    ): SubscriptionCredit {
        $subscription = StoreSubscription::findOrFail($subscriptionId);

        return SubscriptionCredit::create([
            'store_subscription_id' => $subscription->id,
            'applied_by' => $adminUserId,
            'amount' => $amount,
            'reason' => $reason,
            'applied_at' => now(),
        ]);
    }

    // ─── Usage Tracking ──────────────────────────────────────────

    /**
     * Get current usage snapshots for a store.
     */
    public function getUsageSnapshots(string $storeId): Collection
    {
        return SubscriptionUsageSnapshot::where('store_id', $storeId)
            ->where('snapshot_date', today())
            ->get();
    }

    /**
     * Record a usage snapshot.
     */
    public function recordUsage(
        string $storeId,
        string $resourceType,
        int $currentCount,
        int $planLimit,
    ): SubscriptionUsageSnapshot {
        return SubscriptionUsageSnapshot::updateOrCreate(
            [
                'store_id' => $storeId,
                'resource_type' => $resourceType,
                'snapshot_date' => today(),
            ],
            [
                'current_count' => $currentCount,
                'plan_limit' => $planLimit,
            ]
        );
    }
}
