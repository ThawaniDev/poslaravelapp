<?php

namespace App\Domain\ProviderSubscription\Services;

use App\Domain\ProviderSubscription\Models\SoftPosTransaction;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SoftPosService
{
    /**
     * Record a SoftPOS transaction and check if the store qualifies for free subscription.
     *
     * @param  float  $platformFee   Amount charged to the merchant for this transaction.
     * @param  float  $gatewayFee    Amount paid to the payment gateway.
     * @param  float  $margin        Platform net margin (platformFee − gatewayFee).
     * @param  string $feeType       'percentage' | 'fixed'
     */
    public function recordTransaction(
        string $organizationId,
        float $amount,
        ?string $storeId = null,
        ?string $orderId = null,
        ?string $transactionRef = null,
        ?string $paymentMethod = null,
        ?string $terminalId = null,
        array $metadata = [],
        float $platformFee = 0.0,
        float $gatewayFee = 0.0,
        float $margin = 0.0,
        string $feeType = 'percentage',
    ): SoftPosTransaction {
        return DB::transaction(function () use (
            $organizationId, $amount, $storeId, $orderId, $transactionRef, $paymentMethod, $terminalId, $metadata,
            $platformFee, $gatewayFee, $margin, $feeType
        ) {
            $transaction = SoftPosTransaction::create([
                'organization_id' => $organizationId,
                'store_id'        => $storeId,
                'order_id'        => $orderId,
                'amount'          => $amount,
                'platform_fee'    => $platformFee,
                'gateway_fee'     => $gatewayFee,
                'margin'          => $margin,
                'fee_type'        => $feeType,
                'transaction_ref' => $transactionRef,
                'payment_method'  => $paymentMethod,
                'terminal_id'     => $terminalId,
                'status'          => 'completed',
                'metadata'        => $metadata,
            ]);

            // Increment the subscription's softPOS counter and check threshold
            $this->incrementAndCheckThreshold($organizationId, $amount);

            return $transaction;
        });
    }

    /**
     * Increment the softPOS counter on the subscription and check if threshold is reached.
     */
    public function incrementAndCheckThreshold(string $organizationId, float $amount = 0.0): void
    {
        $subscription = StoreSubscription::where('organization_id', $organizationId)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->first();

        if (! $subscription) {
            return;
        }

        $plan = $subscription->subscriptionPlan;
        if (! $plan || ! $plan->softpos_free_eligible) {
            return;
        }

        // Always increment transaction count (useful for analytics)
        $subscription->increment('softpos_transaction_count');

        // Accumulate sales total for amount-based threshold
        if ($amount > 0) {
            $subscription->increment('softpos_sales_total', $amount);
        }

        $subscription->refresh();

        // Check if threshold is reached — amount-based takes priority over count-based
        if ($subscription->is_softpos_free) {
            return;
        }

        $thresholdAmount = $plan->softpos_free_threshold_amount
            ? (float) $plan->softpos_free_threshold_amount
            : null;

        $thresholdCount = $plan->softpos_free_threshold
            ? (int) $plan->softpos_free_threshold
            : null;

        $reached = ($thresholdAmount !== null && $subscription->softpos_sales_total >= $thresholdAmount)
            || ($thresholdAmount === null && $thresholdCount !== null && $subscription->softpos_transaction_count >= $thresholdCount);

        if ($reached) {
            $this->activateSoftPosFree($subscription, $plan);
        }
    }

    /**
     * Activate the free subscription due to SoftPOS threshold being reached.
     */
    private function activateSoftPosFree(StoreSubscription $subscription, SubscriptionPlan $plan): void
    {
        $billingCycle = $subscription->billing_cycle?->value ?? 'monthly';
        $originalAmount = $billingCycle === 'yearly'
            ? (float) $plan->annual_price
            : (float) $plan->monthly_price;

        $subscription->update([
            'is_softpos_free' => true,
            'original_amount' => $originalAmount,
            'discount_reason' => 'softpos_threshold_reached',
        ]);

        Log::info('SoftPOS free subscription activated', [
            'organization_id'    => $subscription->organization_id,
            'softpos_count'      => $subscription->softpos_transaction_count,
            'softpos_sales_total'=> (float) $subscription->softpos_sales_total,
            'threshold_amount'   => $plan->softpos_free_threshold_amount ? (float) $plan->softpos_free_threshold_amount : null,
            'threshold_count'    => $plan->softpos_free_threshold,
            'original_amount'    => $originalAmount,
        ]);
    }

    /**
     * Get the SoftPOS transaction count for an organization in the current period.
     */
    public function getCurrentPeriodCount(string $organizationId): int
    {
        $subscription = StoreSubscription::where('organization_id', $organizationId)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->first();

        return $subscription?->softpos_transaction_count ?? 0;
    }

    /**
     * Get the SoftPOS threshold info for an organization.
     */
    public function getThresholdInfo(string $organizationId): ?array
    {
        $subscription = StoreSubscription::with('subscriptionPlan')
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->first();

        if (! $subscription) {
            return null;
        }

        $plan = $subscription->subscriptionPlan;
        if (! $plan || ! $plan->softpos_free_eligible) {
            return null;
        }

        $threshold = $plan->softpos_free_threshold ?? 0;
        $thresholdAmount = $plan->softpos_free_threshold_amount ? (float) $plan->softpos_free_threshold_amount : null;
        $currentCount = $subscription->softpos_transaction_count;
        $currentSalesTotal = (float) ($subscription->softpos_sales_total ?? 0);

        // Use amount-based progress when an amount threshold is configured
        if ($thresholdAmount !== null && $thresholdAmount > 0) {
            $remaining   = max(0, $thresholdAmount - $currentSalesTotal);
            $percentage  = round(($currentSalesTotal / $thresholdAmount) * 100, 1);
        } else {
            $remaining   = max(0, $threshold - $currentCount);
            $percentage  = $threshold > 0 ? round(($currentCount / $threshold) * 100, 1) : 0;
        }

        $billingCycle = $subscription->billing_cycle?->value ?? 'monthly';
        $subscriptionAmount = $billingCycle === 'yearly'
            ? (float) $plan->annual_price
            : (float) $plan->monthly_price;

        return [
            'is_eligible'            => true,
            'threshold'              => $threshold,
            'threshold_amount'       => $thresholdAmount,
            'threshold_period'       => $plan->softpos_free_threshold_period,
            'current_count'          => $currentCount,
            'current_sales_total'    => $currentSalesTotal,
            'remaining'              => $remaining,
            'percentage'             => min(100, $percentage),
            'is_free'                => $subscription->is_softpos_free,
            'subscription_amount'    => $subscriptionAmount,
            'savings_amount'         => $subscription->is_softpos_free ? $subscriptionAmount : 0,
            'reset_at'               => $subscription->softpos_count_reset_at?->toIso8601String(),
        ];
    }

    /**
     * Reset softPOS counters for subscriptions at the start of a new billing period.
     * Called by scheduled job.
     */
    public function resetPeriodCounters(): int
    {
        $now = now();

        $subscriptions = StoreSubscription::whereHas('subscriptionPlan', function ($q) {
            $q->where('softpos_free_eligible', true)
                ->whereNotNull('softpos_free_threshold_period');
        })
            ->where('status', SubscriptionStatus::Active->value)
            ->with('subscriptionPlan')
            ->get()
            ->filter(function (StoreSubscription $sub) use ($now) {
                $period = $sub->subscriptionPlan?->softpos_free_threshold_period ?? 'monthly';
                $lastReset = $sub->softpos_count_reset_at;

                if (is_null($lastReset)) {
                    return true;
                }

                return match ($period) {
                    'monthly'    => $lastReset->lt($now->copy()->startOfMonth()),
                    'quarterly'  => $lastReset->lt($now->copy()->firstOfQuarter()),
                    'annually'   => $lastReset->lt($now->copy()->startOfYear()),
                    default      => $lastReset->lt($now->copy()->startOfMonth()),
                };
            });

        $count = 0;
        foreach ($subscriptions as $subscription) {
            $subscription->update([
                'softpos_transaction_count' => 0,
                'softpos_sales_total'       => 0,
                'is_softpos_free' => false,
                'softpos_count_reset_at' => now(),
                'original_amount' => null,
                'discount_reason' => null,
            ]);
            $count++;
        }

        if ($count > 0) {
            Log::info('SoftPOS counters reset', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Get SoftPOS transaction history for an organization.
     */
    public function getTransactionHistory(
        string $organizationId,
        int $perPage = 25,
        ?string $startDate = null,
        ?string $endDate = null,
    ) {
        $query = SoftPosTransaction::where('organization_id', $organizationId)
            ->orderByDesc('created_at');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get SoftPOS statistics for an organization.
     */
    public function getStatistics(string $organizationId): array
    {
        $totalCount = SoftPosTransaction::where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->count();

        $totalVolume = SoftPosTransaction::where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->sum('amount');

        $monthlyCount = SoftPosTransaction::where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $monthlyVolume = SoftPosTransaction::where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $thresholdInfo = $this->getThresholdInfo($organizationId);

        return [
            'total_transactions' => $totalCount,
            'total_volume' => round((float) $totalVolume, 3),
            'monthly_transactions' => $monthlyCount,
            'monthly_volume' => round((float) $monthlyVolume, 3),
            'threshold_info' => $thresholdInfo,
        ];
    }
}
