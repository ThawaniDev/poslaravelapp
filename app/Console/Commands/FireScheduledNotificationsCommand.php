<?php

namespace App\Console\Commands;

use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Fires the time-based notification events that don't have a natural
 * model trigger (i.e. cannot be wired through an observer):
 *
 *   - finance.daily_summary       Per-store, end-of-day totals
 *   - inventory.expiry_warning    Products expiring within N days
 *   - system.license_expiring     Subscription within N days of end
 *
 * Run via the scheduler (see app/Console/Kernel.php) once per day.
 */
class FireScheduledNotificationsCommand extends Command
{
    protected $signature = 'notifications:fire-scheduled
                            {--summary : Only fire finance.daily_summary}
                            {--expiry : Only fire inventory.expiry_warning}
                            {--license : Only fire system.license_expiring}';

    protected $description = 'Fire time-based notification events (daily summary, expiry warnings, license expiring).';

    private const EXPIRY_WARNING_DAYS = 14;
    private const LICENSE_WARNING_DAYS = 14;

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $only = [
            'summary' => (bool) $this->option('summary'),
            'expiry' => (bool) $this->option('expiry'),
            'license' => (bool) $this->option('license'),
        ];
        $runAll = ! ($only['summary'] || $only['expiry'] || $only['license']);

        if ($runAll || $only['summary']) {
            $this->fireDailySummaries();
        }
        if ($runAll || $only['expiry']) {
            $this->fireExpiryWarnings();
        }
        if ($runAll || $only['license']) {
            $this->fireLicenseExpiring();
        }

        $this->info('Scheduled notifications dispatched.');

        return self::SUCCESS;
    }

    // ─── finance.daily_summary ───────────────────────────────

    private function fireDailySummaries(): void
    {
        $yesterday = Carbon::yesterday();
        $start = $yesterday->copy()->startOfDay();
        $end = $yesterday->copy()->endOfDay();

        Store::where('is_active', true)->chunkById(50, function ($stores) use ($start, $end, $yesterday) {
            foreach ($stores as $store) {
                $rows = Transaction::where('store_id', $store->id)
                    ->where('status', TransactionStatus::Completed)
                    ->whereBetween('created_at', [$start, $end])
                    ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as total')
                    ->first();

                $count = (int) ($rows->cnt ?? 0);
                $total = (float) ($rows->total ?? 0);
                $currency = $store->currency ?? 'SAR';

                $this->dispatcher->toStoreOwner(
                    storeId: $store->id,
                    eventKey: 'finance.daily_summary',
                    variables: [
                        'date' => $yesterday->toDateString(),
                        'total_sales' => number_format($total, 2) . ' ' . $currency,
                        'total_transactions' => (string) $count,
                        'store_name' => $store->name ?? '',
                    ],
                    category: 'finance',
                    referenceType: 'store',
                    referenceId: $store->id,
                );
            }
        });
    }

    // ─── inventory.expiry_warning ────────────────────────────

    private function fireExpiryWarnings(): void
    {
        $threshold = Carbon::today()->addDays(self::EXPIRY_WARNING_DAYS);

        StockBatch::with(['store', 'product'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $threshold)
            ->where('expiry_date', '>=', Carbon::today())
            ->where('quantity', '>', 0)
            ->chunkById(100, function ($batches) {
                foreach ($batches as $batch) {
                    if (! $batch->store_id || ! $batch->product) {
                        continue;
                    }

                    $daysRemaining = (int) Carbon::today()->diffInDays($batch->expiry_date, false);

                    $this->dispatcher->toStoreOwner(
                        storeId: $batch->store_id,
                        eventKey: 'inventory.expiry_warning',
                        variables: [
                            'product_name' => $batch->product->name ?? '',
                            'expiry_date' => $batch->expiry_date->toDateString(),
                            'days_remaining' => (string) max(0, $daysRemaining),
                            'store_name' => $batch->store?->name ?? '',
                        ],
                        category: 'inventory',
                        referenceType: 'stock_batch',
                        referenceId: $batch->id,
                    );
                }
            });
    }

    // ─── system.license_expiring ─────────────────────────────

    private function fireLicenseExpiring(): void
    {
        $threshold = Carbon::today()->addDays(self::LICENSE_WARNING_DAYS);

        $subs = StoreSubscription::with(['organization', 'subscriptionPlan'])
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial, SubscriptionStatus::Grace])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $threshold)
            ->where('current_period_end', '>=', Carbon::today())
            ->get();

        foreach ($subs as $sub) {
            $orgId = $sub->organization_id;
            if (! $orgId) {
                continue;
            }

            // Notify the owner of every store under the org.
            $stores = Store::where('organization_id', $orgId)->where('is_active', true)->get();
            foreach ($stores as $store) {
                $daysRemaining = (int) Carbon::today()->diffInDays($sub->current_period_end, false);

                $this->dispatcher->toStoreOwner(
                    storeId: $store->id,
                    eventKey: 'system.license_expiring',
                    variables: [
                        'plan_name' => $sub->subscriptionPlan?->name ?? '—',
                        'expiry_date' => $sub->current_period_end->toDateString(),
                        'days_remaining' => (string) max(0, $daysRemaining),
                    ],
                    category: 'system',
                    referenceType: 'subscription',
                    referenceId: $sub->id,
                );
            }
        }
    }
}
