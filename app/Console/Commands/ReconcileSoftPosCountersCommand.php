<?php

namespace App\Console\Commands;

use App\Domain\ProviderSubscription\Models\SoftPosTransaction;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly reconciliation: cross-checks the softpos_transactions table (source of truth)
 * against the denormalized counter on store_subscriptions.
 *
 * This corrects any drift caused by the silent catch in TransactionService without
 * ever blocking a payment transaction at runtime.
 */
class ReconcileSoftPosCountersCommand extends Command
{
    protected $signature = 'pos:reconcile-softpos-counters
                            {--dry-run : Report mismatches without fixing them}
                            {--org= : Limit reconciliation to a single organization UUID}';

    protected $description = 'Reconcile softpos_transaction_count on store_subscriptions against the actual softpos_transactions table';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orgId  = $this->option('org');

        $this->info($dryRun ? '[DRY RUN] Checking SoftPOS counter drift...' : 'Reconciling SoftPOS counters...');

        $query = StoreSubscription::whereHas('subscriptionPlan', fn ($q) =>
            $q->where('softpos_free_eligible', true)
              ->where(fn ($q2) =>
                  $q2->whereNotNull('softpos_free_threshold_amount')
                     ->orWhereNotNull('softpos_free_threshold')
              )
        )
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->with('subscriptionPlan');

        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No eligible subscriptions found.');
            return self::SUCCESS;
        }

        $fixed   = 0;
        $drifted = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $result = $this->reconcile($subscription, $dryRun);

                if ($result === 'fixed') {
                    $fixed++;
                } elseif ($result === 'drifted') {
                    $drifted++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('SoftPOS reconciliation failed for subscription', [
                    'subscription_id' => $subscription->id,
                    'organization_id' => $subscription->organization_id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        $mode = $dryRun ? '[DRY RUN] ' : '';
        if ($dryRun) {
            $this->info("{$mode}Done. Drifted: {$drifted} | Correct: {$skipped} | Errors: {$errors}");
        } else {
            $this->info("{$mode}Done. Fixed: {$fixed} | Correct: {$skipped} | Errors: {$errors}");
        }

        if ($fixed > 0) {
            Log::info('SoftPOS counter reconciliation complete', compact('fixed', 'skipped', 'errors', 'dryRun'));
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────

    private function reconcile(StoreSubscription $subscription, bool $dryRun): string
    {
        $plan = $subscription->subscriptionPlan;

        $periodStart = $this->periodStart(
            $plan->softpos_free_threshold_period ?? 'monthly',
            $subscription->softpos_count_reset_at
        );

        $query = SoftPosTransaction::where('organization_id', $subscription->organization_id)
            ->where('status', 'completed')
            ->where('created_at', '>=', $periodStart);

        // Source of truth: both count and sales total
        $realCount = (int) (clone $query)->count();
        $realSalesTotal = (float) (clone $query)->sum('amount');

        $storedCount      = (int) $subscription->softpos_transaction_count;
        $storedSalesTotal = (float) ($subscription->softpos_sales_total ?? 0);

        $countDrift  = $realCount !== $storedCount;
        $amountDrift = abs($realSalesTotal - $storedSalesTotal) > 0.001; // tolerance for decimal rounding

        if (! $countDrift && ! $amountDrift) {
            return 'ok';
        }

        $this->line(sprintf(
            '  Drift: org=%s | count: stored=%d actual=%d | sales: stored=%.3f actual=%.3f',
            $subscription->organization_id,
            $storedCount,
            $realCount,
            $storedSalesTotal,
            $realSalesTotal
        ));

        if ($dryRun) {
            return 'drifted';
        }

        DB::transaction(function () use ($subscription, $plan, $realCount, $realSalesTotal) {
            $thresholdAmount = $plan->softpos_free_threshold_amount
                ? (float) $plan->softpos_free_threshold_amount
                : null;
            $thresholdCount = (int) ($plan->softpos_free_threshold ?? 0);

            $shouldBeFree = ($thresholdAmount !== null && $realSalesTotal >= $thresholdAmount)
                || ($thresholdAmount === null && $thresholdCount > 0 && $realCount >= $thresholdCount);

            $update = [
                'softpos_transaction_count' => $realCount,
                'softpos_sales_total'       => $realSalesTotal,
            ];

            // Activate free tier if just reached threshold and not yet free
            if ($shouldBeFree && ! $subscription->is_softpos_free) {
                $billingCycle   = $subscription->billing_cycle?->value ?? 'monthly';
                $originalAmount = $billingCycle === 'yearly'
                    ? (float) $plan->annual_price
                    : (float) $plan->monthly_price;

                $update['is_softpos_free']  = true;
                $update['original_amount']  = $originalAmount;
                $update['discount_reason']  = 'softpos_threshold_reached';

                Log::info('SoftPOS free tier activated via reconciliation', [
                    'organization_id'   => $subscription->organization_id,
                    'real_count'        => $realCount,
                    'real_sales_total'  => $realSalesTotal,
                    'threshold_amount'  => $thresholdAmount,
                    'threshold_count'   => $thresholdCount,
                ]);
            }

            // Clear free tier if no longer meets threshold (e.g. manual adjustment)
            if (! $shouldBeFree && $subscription->is_softpos_free) {
                $update['is_softpos_free'] = false;
                $update['original_amount'] = null;
                $update['discount_reason'] = null;

                Log::warning('SoftPOS free tier reversed via reconciliation (below threshold)', [
                    'organization_id'  => $subscription->organization_id,
                    'real_count'       => $realCount,
                    'real_sales_total' => $realSalesTotal,
                    'threshold_amount' => $thresholdAmount,
                    'threshold_count'  => $thresholdCount,
                ]);
            }

            $subscription->update($update);
        });

        return 'fixed';
    }

    /**
     * Determine the start of the current period for the given threshold period type.
     * If the subscription has a manual reset timestamp that is *within* the current
     * period, use that instead — it means the period was already reset.
     */
    private function periodStart(string $period, ?Carbon $lastReset): Carbon
    {
        $naturalStart = match ($period) {
            'quarterly' => now()->firstOfQuarter()->startOfDay(),
            'annually'  => now()->startOfYear()->startOfDay(),
            default     => now()->startOfMonth()->startOfDay(), // monthly
        };

        // If there was a manual reset *after* the natural start, use it as the window
        if ($lastReset && $lastReset->gt($naturalStart)) {
            return $lastReset;
        }

        return $naturalStart;
    }
}
