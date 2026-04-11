<?php

namespace App\Domain\CashierGamification\Services;

use App\Domain\CashierGamification\Enums\AnomalySeverity;
use App\Domain\CashierGamification\Enums\AnomalyType;
use App\Domain\CashierGamification\Enums\PerformancePeriod;
use App\Domain\CashierGamification\Models\CashierAnomaly;
use App\Domain\CashierGamification\Models\CashierGamificationSetting;
use App\Domain\CashierGamification\Models\CashierPerformanceSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CashierAnomalyService
{
    /**
     * Detect anomalies for a given snapshot against store averages.
     *
     * @return CashierAnomaly[]
     */
    public function detectAnomalies(string $storeId, CashierPerformanceSnapshot $snapshot): array
    {
        $settings = CashierGamificationSetting::where('store_id', $storeId)->first();
        $threshold = (float) ($settings->anomaly_z_score_threshold ?? 2.0);

        // Get store averages from recent daily snapshots (last 30 days)
        $stats = CashierPerformanceSnapshot::where('store_id', $storeId)
            ->where('period_type', PerformancePeriod::Daily->value)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->where('cashier_id', '!=', $snapshot->cashier_id)
            ->selectRaw('
                AVG(void_rate) as avg_void_rate, COALESCE(STDDEV(void_rate), 0) as std_void_rate,
                AVG(no_sale_count) as avg_no_sale, COALESCE(STDDEV(no_sale_count), 0) as std_no_sale,
                AVG(discount_rate) as avg_discount_rate, COALESCE(STDDEV(discount_rate), 0) as std_discount_rate,
                AVG(price_override_count) as avg_override, COALESCE(STDDEV(price_override_count), 0) as std_override,
                AVG(cash_variance_absolute) as avg_cash_var, COALESCE(STDDEV(cash_variance_absolute), 0) as std_cash_var
            ')
            ->first();

        if (!$stats) {
            return [];
        }

        $detected = [];

        // Check each metric
        $checks = [
            [
                'type' => AnomalyType::ExcessiveVoids,
                'metric_name' => 'void_rate',
                'value' => (float) $snapshot->void_rate,
                'avg' => (float) $stats->avg_void_rate,
                'std' => (float) $stats->std_void_rate,
                'title_en' => 'Excessive Void Rate',
                'title_ar' => 'معدل إلغاء مرتفع',
                'desc_en' => 'Void rate is significantly above store average.',
                'desc_ar' => 'معدل الإلغاء أعلى بكثير من متوسط المتجر.',
            ],
            [
                'type' => AnomalyType::ExcessiveNoSales,
                'metric_name' => 'no_sale_count',
                'value' => (float) $snapshot->no_sale_count,
                'avg' => (float) $stats->avg_no_sale,
                'std' => (float) $stats->std_no_sale,
                'title_en' => 'Excessive No-Sale Drawer Opens',
                'title_ar' => 'فتح درج نقدي بدون بيع بشكل مفرط',
                'desc_en' => 'Number of no-sale drawer opens is significantly above store average.',
                'desc_ar' => 'عدد مرات فتح الدرج بدون بيع أعلى بكثير من متوسط المتجر.',
            ],
            [
                'type' => AnomalyType::ExcessiveDiscounts,
                'metric_name' => 'discount_rate',
                'value' => (float) $snapshot->discount_rate,
                'avg' => (float) $stats->avg_discount_rate,
                'std' => (float) $stats->std_discount_rate,
                'title_en' => 'Excessive Discount Frequency',
                'title_ar' => 'معدل خصومات مرتفع',
                'desc_en' => 'Discount frequency is significantly above store average.',
                'desc_ar' => 'تكرار الخصومات أعلى بكثير من متوسط المتجر.',
            ],
            [
                'type' => AnomalyType::ExcessivePriceOverrides,
                'metric_name' => 'price_override_count',
                'value' => (float) $snapshot->price_override_count,
                'avg' => (float) $stats->avg_override,
                'std' => (float) $stats->std_override,
                'title_en' => 'Excessive Price Overrides',
                'title_ar' => 'تغييرات أسعار مفرطة',
                'desc_en' => 'Number of price overrides is significantly above store average.',
                'desc_ar' => 'عدد تغييرات الأسعار أعلى بكثير من متوسط المتجر.',
            ],
            [
                'type' => AnomalyType::CashVariance,
                'metric_name' => 'cash_variance_absolute',
                'value' => (float) $snapshot->cash_variance_absolute,
                'avg' => (float) $stats->avg_cash_var,
                'std' => (float) $stats->std_cash_var,
                'title_en' => 'Significant Cash Variance',
                'title_ar' => 'فرق نقدي كبير',
                'desc_en' => 'Cash variance is significantly above store average.',
                'desc_ar' => 'الفرق النقدي أعلى بكثير من متوسط المتجر.',
            ],
        ];

        foreach ($checks as $check) {
            $zScore = $this->zScore($check['value'], $check['avg'], $check['std']);
            if ($zScore >= $threshold && $check['value'] > 0) {
                $severity = $this->severityFromZScore($zScore);

                $detected[] = CashierAnomaly::create([
                    'store_id' => $storeId,
                    'cashier_id' => $snapshot->cashier_id,
                    'snapshot_id' => $snapshot->id,
                    'anomaly_type' => $check['type']->value,
                    'severity' => $severity->value,
                    'risk_score' => round(min(100, $zScore * 25), 2),
                    'title_en' => $check['title_en'],
                    'title_ar' => $check['title_ar'],
                    'description_en' => $check['desc_en'],
                    'description_ar' => $check['desc_ar'],
                    'metric_name' => $check['metric_name'],
                    'metric_value' => $check['value'],
                    'store_average' => $check['avg'],
                    'store_stddev' => $check['std'],
                    'z_score' => round($zScore, 2),
                    'detected_date' => $snapshot->date,
                ]);
            }
        }

        return $detected;
    }

    /**
     * List anomalies for a store with filters.
     */
    public function list(string $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CashierAnomaly::where('store_id', $storeId)
            ->with('cashier:id,name,email');

        if (!empty($filters['cashier_id'])) {
            $query->where('cashier_id', $filters['cashier_id']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['anomaly_type'])) {
            $query->where('anomaly_type', $filters['anomaly_type']);
        }

        if (!empty($filters['is_reviewed'])) {
            $query->where('is_reviewed', $filters['is_reviewed'] === 'true');
        }

        if (!empty($filters['date_from'])) {
            $query->where('detected_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('detected_date', '<=', $filters['date_to']);
        }

        $query->orderBy('detected_date', 'desc')
            ->orderBy('risk_score', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Mark an anomaly as reviewed.
     */
    public function review(CashierAnomaly $anomaly, string $reviewerId, ?string $notes = null): CashierAnomaly
    {
        $anomaly->update([
            'is_reviewed' => true,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $anomaly->fresh();
    }

    private function zScore(float $value, float $mean, float $stddev): float
    {
        if ($stddev <= 0) {
            return $value > $mean ? 2.0 : 0.0;
        }
        return ($value - $mean) / $stddev;
    }

    private function severityFromZScore(float $zScore): AnomalySeverity
    {
        return match (true) {
            $zScore >= 4 => AnomalySeverity::Critical,
            $zScore >= 3 => AnomalySeverity::High,
            $zScore >= 2 => AnomalySeverity::Medium,
            default => AnomalySeverity::Low,
        };
    }
}
