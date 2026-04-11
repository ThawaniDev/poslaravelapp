<?php

namespace App\Domain\CashierGamification\Services;

use App\Domain\CashierGamification\Enums\PerformancePeriod;
use App\Domain\CashierGamification\Models\CashierGamificationSetting;
use App\Domain\CashierGamification\Models\CashierPerformanceSnapshot;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Enums\TransactionType;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CashierPerformanceService
{
    /**
     * Calculate and store a performance snapshot for a shift (POS session close).
     */
    public function calculateShiftSnapshot(string $storeId, PosSession $session): CashierPerformanceSnapshot
    {
        $cashierId = $session->cashier_id;
        $openedAt = $session->opened_at ? Carbon::parse($session->opened_at) : null;
        $closedAt = $session->closed_at ? Carbon::parse($session->closed_at) : now();
        $activeMinutes = $openedAt ? (int) $openedAt->diffInMinutes($closedAt) : 0;
        $activeMinutes = max($activeMinutes, 1); // avoid division by zero

        // Fetch transactions for this session
        $transactions = Transaction::where('store_id', $storeId)
            ->where('pos_session_id', $session->id)
            ->where('cashier_id', $cashierId)
            ->get();

        $sales = $transactions->filter(fn ($t) => $t->type === TransactionType::Sale && $t->status === TransactionStatus::Completed);
        $voids = $transactions->filter(fn ($t) => $t->status === TransactionStatus::Voided || $t->type === TransactionType::Void);
        $returns = $transactions->filter(fn ($t) => $t->type === TransactionType::Return);

        $totalTransactions = $sales->count();
        $totalRevenue = (float) $sales->sum('total_amount');
        $totalDiscountGiven = (float) $sales->sum('discount_amount');
        $avgBasketSize = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // Count items sold
        $saleIds = $sales->pluck('id')->toArray();
        $totalItemsSold = $saleIds ? (int) DB::table('transaction_items')
            ->whereIn('transaction_id', $saleIds)
            ->sum('quantity') : 0;

        $itemsPerMinute = $activeMinutes > 0 ? round($totalItemsSold / $activeMinutes, 2) : 0;
        $avgTransactionTimeSec = $totalTransactions > 0 ? (int) round(($activeMinutes * 60) / $totalTransactions) : 0;

        // Voids
        $voidCount = $voids->count();
        $voidAmount = (float) $voids->sum('total_amount');
        $voidRate = $totalTransactions > 0 ? round($voidCount / $totalTransactions, 4) : 0;

        // Returns
        $returnCount = $returns->count();
        $returnAmount = (float) $returns->sum('total_amount');

        // Discounts
        $discountCount = $sales->filter(fn ($t) => (float) $t->discount_amount > 0)->count();
        $discountRate = $totalTransactions > 0 ? round($discountCount / $totalTransactions, 4) : 0;

        // Price overrides — transactions with notes indicating override (count from items)
        $priceOverrideCount = $saleIds ? (int) DB::table('transaction_items')
            ->whereIn('transaction_id', $saleIds)
            ->where('is_price_override', true)
            ->count() : 0;

        // No-sale drawer opens
        $noSaleCount = (int) DB::table('cash_events')
            ->where('store_id', $storeId)
            ->where('performed_by', $cashierId)
            ->where('type', 'no_sale')
            ->when($openedAt, fn ($q) => $q->where('created_at', '>=', $openedAt))
            ->where('created_at', '<=', $closedAt)
            ->count();

        // Upsells — count transactions with more than average items
        $avgItemsPerTx = $totalTransactions > 0 ? $totalItemsSold / $totalTransactions : 0;
        $upsellCount = 0;
        if ($totalTransactions > 0 && $avgItemsPerTx > 0) {
            $upsellCount = (int) DB::table('transaction_items')
                ->selectRaw('transaction_id, SUM(quantity) as item_count')
                ->whereIn('transaction_id', $saleIds)
                ->groupBy('transaction_id')
                ->having('item_count', '>', ceil($avgItemsPerTx))
                ->count();
        }
        $upsellRate = $totalTransactions > 0 ? round($upsellCount / $totalTransactions, 4) : 0;

        // Cash variance
        $cashVariance = (float) ($session->cash_difference ?? 0);
        $cashVarianceAbsolute = abs($cashVariance);

        // Calculate risk score
        $riskScore = $this->calculateRiskScore($storeId, [
            'void_rate' => $voidRate,
            'no_sale_count' => $noSaleCount,
            'discount_rate' => $discountRate,
            'price_override_count' => $priceOverrideCount,
        ]);

        return CashierPerformanceSnapshot::updateOrCreate(
            [
                'store_id' => $storeId,
                'cashier_id' => $cashierId,
                'date' => $closedAt->toDateString(),
                'period_type' => PerformancePeriod::Shift->value,
                'pos_session_id' => $session->id,
            ],
            [
                'shift_start' => $openedAt,
                'shift_end' => $closedAt,
                'active_minutes' => $activeMinutes,
                'total_transactions' => $totalTransactions,
                'total_items_sold' => $totalItemsSold,
                'total_revenue' => $totalRevenue,
                'total_discount_given' => $totalDiscountGiven,
                'avg_basket_size' => round($avgBasketSize, 2),
                'items_per_minute' => $itemsPerMinute,
                'avg_transaction_time_seconds' => $avgTransactionTimeSec,
                'void_count' => $voidCount,
                'void_amount' => $voidAmount,
                'void_rate' => $voidRate,
                'return_count' => $returnCount,
                'return_amount' => $returnAmount,
                'discount_count' => $discountCount,
                'discount_rate' => $discountRate,
                'price_override_count' => $priceOverrideCount,
                'no_sale_count' => $noSaleCount,
                'upsell_count' => $upsellCount,
                'upsell_rate' => $upsellRate,
                'cash_variance' => $cashVariance,
                'cash_variance_absolute' => $cashVarianceAbsolute,
                'risk_score' => $riskScore,
            ]
        );
    }

    /**
     * Calculate daily aggregate snapshot for a cashier.
     */
    public function calculateDailySnapshot(string $storeId, string $cashierId, string $date): CashierPerformanceSnapshot
    {
        $shiftSnapshots = CashierPerformanceSnapshot::where('store_id', $storeId)
            ->where('cashier_id', $cashierId)
            ->whereDate('date', $date)
            ->where('period_type', PerformancePeriod::Shift->value)
            ->get();

        $totalTransactions = $shiftSnapshots->sum('total_transactions');
        $totalRevenue = (float) $shiftSnapshots->sum('total_revenue');
        $totalItemsSold = $shiftSnapshots->sum('total_items_sold');
        $activeMinutes = $shiftSnapshots->sum('active_minutes');
        $voidCount = $shiftSnapshots->sum('void_count');
        $discountCount = $shiftSnapshots->sum('discount_count');

        return CashierPerformanceSnapshot::updateOrCreate(
            [
                'store_id' => $storeId,
                'cashier_id' => $cashierId,
                'date' => $date,
                'period_type' => PerformancePeriod::Daily->value,
                'pos_session_id' => null,
            ],
            [
                'active_minutes' => $activeMinutes,
                'total_transactions' => $totalTransactions,
                'total_items_sold' => $totalItemsSold,
                'total_revenue' => $totalRevenue,
                'total_discount_given' => (float) $shiftSnapshots->sum('total_discount_given'),
                'avg_basket_size' => $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0,
                'items_per_minute' => $activeMinutes > 0 ? round($totalItemsSold / $activeMinutes, 2) : 0,
                'avg_transaction_time_seconds' => $totalTransactions > 0 ? (int) round(($activeMinutes * 60) / $totalTransactions) : 0,
                'void_count' => $voidCount,
                'void_amount' => (float) $shiftSnapshots->sum('void_amount'),
                'void_rate' => $totalTransactions > 0 ? round($voidCount / $totalTransactions, 4) : 0,
                'return_count' => $shiftSnapshots->sum('return_count'),
                'return_amount' => (float) $shiftSnapshots->sum('return_amount'),
                'discount_count' => $discountCount,
                'discount_rate' => $totalTransactions > 0 ? round($discountCount / $totalTransactions, 4) : 0,
                'price_override_count' => $shiftSnapshots->sum('price_override_count'),
                'no_sale_count' => $shiftSnapshots->sum('no_sale_count'),
                'upsell_count' => $shiftSnapshots->sum('upsell_count'),
                'upsell_rate' => $totalTransactions > 0
                    ? round($shiftSnapshots->sum('upsell_count') / $totalTransactions, 4) : 0,
                'cash_variance' => (float) $shiftSnapshots->sum('cash_variance'),
                'cash_variance_absolute' => (float) $shiftSnapshots->sum('cash_variance_absolute'),
                'risk_score' => $shiftSnapshots->count() > 0
                    ? round($shiftSnapshots->avg('risk_score'), 2) : 0,
            ]
        );
    }

    /**
     * Get the leaderboard for a store on a given date/period.
     */
    public function getLeaderboard(string $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CashierPerformanceSnapshot::where('store_id', $storeId)
            ->with('cashier:id,name,email');

        if (!empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        } else {
            $query->whereDate('date', now()->toDateString());
        }

        $periodType = $filters['period_type'] ?? 'daily';
        $query->where('period_type', $periodType);

        $sortBy = $filters['sort_by'] ?? 'total_revenue';
        $allowedSorts = [
            'total_revenue', 'items_per_minute', 'total_transactions',
            'avg_basket_size', 'void_rate', 'upsell_rate', 'risk_score',
        ];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'total_revenue';
        }

        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get performance history for a single cashier.
     */
    public function getCashierHistory(string $storeId, string $cashierId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CashierPerformanceSnapshot::where('store_id', $storeId)
            ->where('cashier_id', $cashierId);

        if (!empty($filters['period_type'])) {
            $query->where('period_type', $filters['period_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        $query->orderBy('date', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Calculate risk score based on z-scores.
     */
    public function calculateRiskScore(string $storeId, array $metrics): float
    {
        $settings = CashierGamificationSetting::where('store_id', $storeId)->first();

        $voidWeight = (float) ($settings->risk_score_void_weight ?? 30);
        $noSaleWeight = (float) ($settings->risk_score_no_sale_weight ?? 25);
        $discountWeight = (float) ($settings->risk_score_discount_weight ?? 25);
        $overrideWeight = (float) ($settings->risk_score_price_override_weight ?? 20);

        // Get store averages from recent daily snapshots (last 30 days)
        $recentStats = CashierPerformanceSnapshot::where('store_id', $storeId)
            ->where('period_type', PerformancePeriod::Daily->value)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('
                COUNT(*) as sample_count,
                AVG(void_rate) as avg_void_rate,
                COALESCE(STDDEV(void_rate), 0) as std_void_rate,
                AVG(no_sale_count) as avg_no_sale,
                COALESCE(STDDEV(no_sale_count), 0) as std_no_sale,
                AVG(discount_rate) as avg_discount_rate,
                COALESCE(STDDEV(discount_rate), 0) as std_discount_rate,
                AVG(price_override_count) as avg_price_override,
                COALESCE(STDDEV(price_override_count), 0) as std_price_override
            ')
            ->first();

        // Need at least 3 data points to compute meaningful risk score
        if (!$recentStats || (int) $recentStats->sample_count < 3) {
            return 0;
        }

        $zVoid = $this->zScore((float) ($metrics['void_rate'] ?? 0), (float) $recentStats->avg_void_rate, (float) $recentStats->std_void_rate);
        $zNoSale = $this->zScore((float) ($metrics['no_sale_count'] ?? 0), (float) $recentStats->avg_no_sale, (float) $recentStats->std_no_sale);
        $zDiscount = $this->zScore((float) ($metrics['discount_rate'] ?? 0), (float) $recentStats->avg_discount_rate, (float) $recentStats->std_discount_rate);
        $zOverride = $this->zScore((float) ($metrics['price_override_count'] ?? 0), (float) $recentStats->avg_price_override, (float) $recentStats->std_price_override);

        // Weighted sum, clamped to 0-100
        $totalWeight = $voidWeight + $noSaleWeight + $discountWeight + $overrideWeight;
        $rawScore = ($zVoid * $voidWeight + $zNoSale * $noSaleWeight + $zDiscount * $discountWeight + $zOverride * $overrideWeight) / $totalWeight;

        // Normalize: map z-score sum to 0-100 range
        $normalized = min(100, max(0, $rawScore * 25));

        return round($normalized, 2);
    }

    private function zScore(float $value, float $mean, float $stddev): float
    {
        if ($stddev <= 0) {
            return $value > $mean ? 2.0 : 0.0;
        }
        return max(0, ($value - $mean) / $stddev);
    }
}
