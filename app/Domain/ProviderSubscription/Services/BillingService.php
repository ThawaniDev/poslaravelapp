<?php

namespace App\Domain\ProviderSubscription\Services;

use App\Domain\Billing\Models\HardwareSale;
use App\Domain\Billing\Models\ImplementationFee;
use App\Domain\Billing\Models\PaymentRetryRule;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\InvoiceLineItem;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Models\SubscriptionCredit;
use App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot;
use App\Domain\ProviderSubscription\Models\StoreAddOn;
use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingService
{
    // ─── Subscription Queries ────────────────────────────────────

    /**
     * Get the current subscription for an organization.
     */
    public function getCurrentSubscription(string $organizationId): ?StoreSubscription
    {
        return StoreSubscription::with(['subscriptionPlan.planFeatureToggles', 'subscriptionPlan.planLimits'])
            ->where('organization_id', $organizationId)
            ->first();
    }

    /**
     * Get invoice history for an organization.
     */
    public function getInvoices(string $organizationId, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Invoice::with(['invoiceLineItems'])
            ->whereHas('storeSubscription', fn ($q) => $q->where('organization_id', $organizationId))
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
     * Subscribe an organization to a plan.
     *
     * @throws \RuntimeException if organization already has active subscription
     */
    public function subscribe(
        string $organizationId,
        string $planId,
        BillingCycle $billingCycle = BillingCycle::Monthly,
        ?string $paymentMethod = null,
    ): StoreSubscription {
        return DB::transaction(function () use ($organizationId, $planId, $billingCycle, $paymentMethod) {
            // Check for existing active subscription
            $existing = StoreSubscription::where('organization_id', $organizationId)
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trial->value,
                    SubscriptionStatus::Grace->value,
                ])
                ->first();

            if ($existing) {
                throw new \RuntimeException(
                    'Organization already has an active subscription. Use changePlan() to switch plans.'
                );
            }

            $plan = SubscriptionPlan::findOrFail($planId);

            if (! $plan->is_active) {
                throw new \RuntimeException("Plan '{$plan->name}' is not currently available.");
            }

            $now = now();
            $hasTrial = $plan->trial_days && $plan->trial_days > 0;

            $subscription = StoreSubscription::create([
                'organization_id' => $organizationId,
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
        string $organizationId,
        string $newPlanId,
        BillingCycle $billingCycle = BillingCycle::Monthly,
    ): StoreSubscription {
        return DB::transaction(function () use ($organizationId, $newPlanId, $billingCycle) {
            $subscription = StoreSubscription::where('organization_id', $organizationId)
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
                throw new \RuntimeException('Organization is already on this plan.');
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
    public function cancelSubscription(string $organizationId, ?string $reason = null): StoreSubscription
    {
        $subscription = StoreSubscription::where('organization_id', $organizationId)
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
    public function resumeSubscription(string $organizationId): StoreSubscription
    {
        $subscription = StoreSubscription::where('organization_id', $organizationId)
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

    private const VAT_RATE = 0.15; // 15% Saudi VAT

    /**
     * Generate a unique sequential invoice number.
     */
    private function nextInvoiceNumber(): string
    {
        $latest = Invoice::query()
            ->where('invoice_number', 'like', 'INV-%')
            ->orderByDesc('created_at')
            ->value('invoice_number');

        $sequence = 1;
        if ($latest && preg_match('/INV-(\d+)/', $latest, $m)) {
            $sequence = (int) $m[1] + 1;
        }

        return 'INV-' . str_pad($sequence, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a full subscription invoice (plan + active add-ons + credits applied).
     */
    public function generateInvoice(StoreSubscription $subscription, ?string $description = null): Invoice
    {
        $plan = $subscription->subscriptionPlan;
        $billingCycle = $subscription->billing_cycle ?? BillingCycle::Monthly;

        $lineItems = [];

        // ── 1. Plan subscription line ─────────────────────────────
        $planAmount = $billingCycle === BillingCycle::Yearly
            ? (float) $plan->annual_price
            : (float) $plan->monthly_price;

        // Check if subscription is free due to SoftPOS threshold
        $isSoftPosFree = $subscription->is_softpos_free
            && $plan->softpos_free_eligible
            && $subscription->softpos_transaction_count >= ($plan->softpos_free_threshold ?? 0);

        $lineItems[] = [
            'description' => $description ?? "{$plan->name} — {$billingCycle->value} subscription",
            'quantity' => 1,
            'unit_price' => $planAmount,
            'total' => $planAmount,
        ];

        // Apply SoftPOS free discount
        if ($isSoftPosFree && $planAmount > 0) {
            $lineItems[] = [
                'description' => "SoftPOS Free Tier Discount (Reached {$subscription->softpos_transaction_count}/{$plan->softpos_free_threshold} transactions)",
                'quantity' => 1,
                'unit_price' => -$planAmount,
                'total' => -$planAmount,
            ];
        }

        // ── 2. Active add-ons across all stores in the organization ──
        $storeIds = Store::where('organization_id', $subscription->organization_id)->pluck('id');

        $activeAddOns = StoreAddOn::whereIn('store_id', $storeIds)
            ->where('is_active', true)
            ->with('planAddOn')
            ->get();

        foreach ($activeAddOns as $storeAddOn) {
            $addOn = $storeAddOn->planAddOn;
            if (! $addOn) {
                continue;
            }

            $addOnPrice = (float) $addOn->monthly_price;
            if ($addOnPrice <= 0) {
                continue;
            }

            // Scale add-on price to billing cycle
            $addOnTotal = $billingCycle === BillingCycle::Yearly
                ? round($addOnPrice * 12, 2)
                : $addOnPrice;

            $lineItems[] = [
                'description' => "Add-on: {$addOn->name}" . ($billingCycle === BillingCycle::Yearly ? ' (annual)' : ''),
                'quantity' => 1,
                'unit_price' => $addOnTotal,
                'total' => $addOnTotal,
            ];
        }

        // ── 3. Calculate totals ───────────────────────────────────
        $subtotal = collect($lineItems)->sum('total');

        // ── 4. Apply unused credits ───────────────────────────────
        $availableCredits = $this->getAvailableCredits($subscription->id);
        $creditApplied = 0;

        if ($availableCredits > 0) {
            $creditApplied = min($availableCredits, $subtotal);
            if ($creditApplied > 0) {
                $lineItems[] = [
                    'description' => 'Credit applied',
                    'quantity' => 1,
                    'unit_price' => -$creditApplied,
                    'total' => -$creditApplied,
                ];
                $subtotal -= $creditApplied;
            }
        }

        $tax = round($subtotal * self::VAT_RATE, 2);
        $total = round($subtotal + $tax, 2);

        // ── 5. Create invoice ─────────────────────────────────────
        return DB::transaction(function () use ($subscription, $lineItems, $subtotal, $tax, $total) {
            $invoice = Invoice::create([
                'store_subscription_id' => $subscription->id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'amount' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending',
                'due_date' => now()->addDays(7),
            ]);

            foreach ($lineItems as $item) {
                InvoiceLineItem::create(array_merge($item, [
                    'invoice_id' => $invoice->id,
                ]));
            }

            return $invoice->load('invoiceLineItems');
        });
    }

    /**
     * Get total unused credits for a subscription.
     * Credits are one-time; once an invoice with credit is generated, the credit is consumed.
     */
    public function getAvailableCredits(string $subscriptionId): float
    {
        $totalCredits = SubscriptionCredit::where('store_subscription_id', $subscriptionId)
            ->sum('amount');

        $totalUsed = InvoiceLineItem::whereHas('invoice', fn ($q) => $q->where('store_subscription_id', $subscriptionId))
            ->where('description', 'Credit applied')
            ->where('unit_price', '<', 0)
            ->sum(DB::raw('ABS(total)'));

        return max(0, (float) $totalCredits - (float) $totalUsed);
    }

    /**
     * Generate renewal invoices for subscriptions expiring within N days.
     * Called by the scheduled GenerateRenewalInvoices job.
     */
    public function generateRenewalInvoices(int $daysBeforeExpiry = 3): array
    {
        $cutoff = now()->addDays($daysBeforeExpiry);

        $subscriptions = StoreSubscription::with(['subscriptionPlan', 'invoices'])
            ->where('status', SubscriptionStatus::Active->value)
            ->where('current_period_end', '<=', $cutoff)
            ->where('current_period_end', '>', now())
            ->get();

        $generated = [];

        foreach ($subscriptions as $subscription) {
            // Skip if a pending invoice already exists for this period
            $hasPendingInvoice = $subscription->invoices()
                ->where('status', 'pending')
                ->where('created_at', '>=', $subscription->current_period_start)
                ->exists();

            if ($hasPendingInvoice) {
                continue;
            }

            try {
                $invoice = $this->generateInvoice($subscription, 'Renewal — ' . $subscription->subscriptionPlan->name);
                $generated[] = $invoice;

                Log::info('Renewal invoice generated', [
                    'invoice_id' => $invoice->id,
                    'organization_id' => $subscription->organization_id,
                    'total' => $invoice->total,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to generate renewal invoice', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $generated;
    }

    /**
     * Retry failed invoice payments based on payment_retry_rules config.
     * Called by the scheduled RetryFailedPayments job.
     */
    public function retryFailedPayments(): array
    {
        $rules = PaymentRetryRule::first();
        $maxRetries = $rules?->max_retries ?? 3;
        $retryIntervalHours = $rules?->retry_interval_hours ?? 24;
        $gracePeriodDays = $rules?->grace_period_after_failure_days ?? 7;

        $failedInvoices = Invoice::with('storeSubscription')
            ->where('status', 'failed')
            ->where('due_date', '>=', now()->subDays($gracePeriodDays))
            ->get();

        $results = [];

        foreach ($failedInvoices as $invoice) {
            $subscription = $invoice->storeSubscription;
            if (! $subscription) {
                continue;
            }

            // Count previous retry attempts (failed invoices in the same period)
            $retryCount = Invoice::where('store_subscription_id', $subscription->id)
                ->where('status', 'failed')
                ->where('invoice_number', $invoice->invoice_number)
                ->count();

            if ($retryCount >= $maxRetries) {
                // Max retries exceeded — move subscription to grace
                if (($subscription->status?->value ?? $subscription->status) === 'active') {
                    $plan = $subscription->subscriptionPlan;
                    $subscription->update([
                        'status' => SubscriptionStatus::Grace,
                        'current_period_end' => now()->addDays($plan->grace_period_days ?? $gracePeriodDays),
                    ]);

                    Log::warning('Subscription moved to grace after max retries', [
                        'subscription_id' => $subscription->id,
                        'organization_id' => $subscription->organization_id,
                    ]);
                }
                continue;
            }

            // Check if enough time has passed since last attempt
            $lastAttempt = $invoice->updated_at;
            if ($lastAttempt && $lastAttempt->diffInHours(now()) < $retryIntervalHours) {
                continue;
            }

            // TODO: Integrate with actual payment gateway here
            // For now, mark as pending for manual processing
            $invoice->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);

            $results[] = [
                'invoice_id' => $invoice->id,
                'organization_id' => $subscription->organization_id,
                'retry_count' => $retryCount + 1,
            ];

            Log::info('Failed payment retry attempted', [
                'invoice_id' => $invoice->id,
                'retry_count' => $retryCount + 1,
            ]);
        }

        return $results;
    }

    /**
     * Expire subscriptions that are past their grace period.
     * Called by the scheduled ExpireSubscriptions job.
     */
    public function expireOverdueSubscriptions(): int
    {
        $expired = StoreSubscription::where('status', SubscriptionStatus::Grace->value)
            ->where('current_period_end', '<', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        // Also expire trial subscriptions past their trial date
        $trialExpired = StoreSubscription::where('status', SubscriptionStatus::Trial->value)
            ->where('trial_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        if ($expired + $trialExpired > 0) {
            Log::info('Subscriptions expired', ['grace' => $expired, 'trial' => $trialExpired]);
        }

        return $expired + $trialExpired;
    }

    /**
     * Renew subscriptions whose period has ended and have a paid invoice.
     * Called by the scheduled RenewPaidSubscriptions job.
     */
    public function renewPaidSubscriptions(): int
    {
        $subscriptions = StoreSubscription::where('status', SubscriptionStatus::Active->value)
            ->where('current_period_end', '<=', now())
            ->get();

        $renewed = 0;

        foreach ($subscriptions as $subscription) {
            // Check for a paid invoice in the current period
            $hasPaidInvoice = $subscription->invoices()
                ->where('status', 'paid')
                ->where('created_at', '>=', $subscription->current_period_start)
                ->exists();

            if (! $hasPaidInvoice) {
                continue; // Will be handled by retry/grace logic
            }

            $billingCycle = $subscription->billing_cycle ?? BillingCycle::Monthly;
            $newEnd = $billingCycle === BillingCycle::Yearly
                ? now()->addYear()
                : now()->addMonth();

            $subscription->update([
                'current_period_start' => now(),
                'current_period_end' => $newEnd,
            ]);

            $renewed++;
        }

        if ($renewed > 0) {
            Log::info('Subscriptions renewed', ['count' => $renewed]);
        }

        return $renewed;
    }

    /**
     * Generate an invoice for an implementation fee.
     */
    public function generateImplementationFeeInvoice(ImplementationFee $fee): ?Invoice
    {
        $store = Store::find($fee->store_id);
        if (! $store) {
            return null;
        }

        $subscription = StoreSubscription::where('organization_id', $store->organization_id)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->first();

        if (! $subscription) {
            Log::warning('Cannot generate implementation fee invoice — no active subscription', [
                'store_id' => $fee->store_id,
                'organization_id' => $store->organization_id,
                'fee_id' => $fee->id,
            ]);

            return null;
        }

        $amount = (float) $fee->amount;
        $tax = round($amount * self::VAT_RATE, 2);
        $total = round($amount + $tax, 2);

        $feeType = $fee->fee_type instanceof \BackedEnum ? $fee->fee_type->value : $fee->fee_type;

        return DB::transaction(function () use ($subscription, $fee, $amount, $tax, $total, $feeType) {
            $invoice = Invoice::create([
                'store_subscription_id' => $subscription->id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'amount' => $amount,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending',
                'due_date' => now()->addDays(14),
            ]);

            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Implementation fee — {$feeType}",
                'quantity' => 1,
                'unit_price' => $amount,
                'total' => $amount,
            ]);

            return $invoice->load('invoiceLineItems');
        });
    }

    /**
     * Generate an invoice for a hardware sale.
     */
    public function generateHardwareSaleInvoice(HardwareSale $sale): ?Invoice
    {
        $store = Store::find($sale->store_id);
        if (! $store) {
            return null;
        }

        $subscription = StoreSubscription::where('organization_id', $store->organization_id)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->first();

        if (! $subscription) {
            Log::warning('Cannot generate hardware sale invoice — no active subscription', [
                'store_id' => $sale->store_id,
                'organization_id' => $store->organization_id,
                'sale_id' => $sale->id,
            ]);

            return null;
        }

        $amount = (float) $sale->amount;
        $tax = round($amount * self::VAT_RATE, 2);
        $total = round($amount + $tax, 2);

        $itemType = $sale->item_type instanceof \BackedEnum ? $sale->item_type->value : $sale->item_type;

        return DB::transaction(function () use ($subscription, $sale, $amount, $tax, $total, $itemType) {
            $invoice = Invoice::create([
                'store_subscription_id' => $subscription->id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'amount' => $amount,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending',
                'due_date' => now()->addDays(14),
            ]);

            $desc = "Hardware: {$itemType}";
            if ($sale->item_description) {
                $desc .= " — {$sale->item_description}";
            }
            if ($sale->serial_number) {
                $desc .= " (S/N: {$sale->serial_number})";
            }

            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'description' => $desc,
                'quantity' => 1,
                'unit_price' => $amount,
                'total' => $amount,
            ]);

            return $invoice->load('invoiceLineItems');
        });
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
     * Get current usage snapshots for an organization.
     */
    public function getUsageSnapshots(string $organizationId): Collection
    {
        return SubscriptionUsageSnapshot::where('organization_id', $organizationId)
            ->where('snapshot_date', today())
            ->get();
    }

    /**
     * Record a usage snapshot.
     */
    public function recordUsage(
        string $organizationId,
        string $resourceType,
        int $currentCount,
        int $planLimit,
    ): SubscriptionUsageSnapshot {
        return SubscriptionUsageSnapshot::updateOrCreate(
            [
                'organization_id' => $organizationId,
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
