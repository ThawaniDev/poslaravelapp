<?php

namespace App\Domain\CashierGamification\Services;

use App\Domain\CashierGamification\Enums\RiskLevel;
use App\Domain\CashierGamification\Models\CashierAnomaly;
use App\Domain\CashierGamification\Models\CashierBadgeAward;
use App\Domain\CashierGamification\Models\CashierPerformanceSnapshot;
use App\Domain\CashierGamification\Models\CashierShiftReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CashierShiftReportService
{
    /**
     * Generate a shift-end report from a performance snapshot.
     */
    public function generateReport(string $storeId, CashierPerformanceSnapshot $snapshot): CashierShiftReport
    {
        $cashierId = $snapshot->cashier_id;
        $date = $snapshot->date;

        // Count anomalies for this snapshot
        $anomalyCount = CashierAnomaly::where('store_id', $storeId)
            ->where('cashier_id', $cashierId)
            ->where('snapshot_id', $snapshot->id)
            ->count();

        // Get badges earned today
        $badges = CashierBadgeAward::where('store_id', $storeId)
            ->where('cashier_id', $cashierId)
            ->where('earned_date', $date)
            ->with('badge:id,slug,name_en,name_ar,icon,color')
            ->get()
            ->map(fn ($award) => [
                'badge_id' => $award->badge_id,
                'slug' => $award->badge?->slug,
                'name_en' => $award->badge?->name_en,
                'name_ar' => $award->badge?->name_ar,
                'icon' => $award->badge?->icon,
                'color' => $award->badge?->color,
                'metric_value' => (float) $award->metric_value,
            ])
            ->toArray();

        $riskScore = (float) $snapshot->risk_score;
        $riskLevel = RiskLevel::fromScore($riskScore);

        // Build summary
        $summaryEn = $this->buildSummaryEn($snapshot, $anomalyCount, count($badges), $riskLevel);
        $summaryAr = $this->buildSummaryAr($snapshot, $anomalyCount, count($badges), $riskLevel);

        return CashierShiftReport::updateOrCreate(
            [
                'store_id' => $storeId,
                'cashier_id' => $cashierId,
                'pos_session_id' => $snapshot->pos_session_id,
            ],
            [
                'report_date' => $date,
                'shift_start' => $snapshot->shift_start,
                'shift_end' => $snapshot->shift_end,
                'total_transactions' => $snapshot->total_transactions,
                'total_revenue' => $snapshot->total_revenue,
                'total_items' => $snapshot->total_items_sold,
                'items_per_minute' => $snapshot->items_per_minute,
                'avg_basket_size' => $snapshot->avg_basket_size,
                'void_count' => $snapshot->void_count,
                'void_amount' => $snapshot->void_amount,
                'return_count' => $snapshot->return_count,
                'return_amount' => $snapshot->return_amount,
                'discount_count' => $snapshot->discount_count,
                'discount_amount' => $snapshot->total_discount_given,
                'no_sale_count' => $snapshot->no_sale_count,
                'price_override_count' => $snapshot->price_override_count,
                'cash_variance' => $snapshot->cash_variance,
                'upsell_count' => $snapshot->upsell_count,
                'upsell_rate' => $snapshot->upsell_rate,
                'risk_score' => $riskScore,
                'risk_level' => $riskLevel->value,
                'anomaly_count' => $anomalyCount,
                'badges_earned' => $badges,
                'summary_en' => $summaryEn,
                'summary_ar' => $summaryAr,
            ]
        );
    }

    /**
     * List shift reports for a store.
     */
    public function list(string $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CashierShiftReport::where('store_id', $storeId)
            ->with('cashier:id,name,email');

        if (!empty($filters['cashier_id'])) {
            $query->where('cashier_id', $filters['cashier_id']);
        }
        if (!empty($filters['risk_level'])) {
            $query->where('risk_level', $filters['risk_level']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('report_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('report_date', '<=', $filters['date_to']);
        }

        $query->orderBy('report_date', 'desc')
            ->orderBy('risk_score', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Find a shift report by ID.
     */
    public function find(string $id): ?CashierShiftReport
    {
        return CashierShiftReport::with('cashier:id,name,email')->find($id);
    }

    /**
     * Mark a report as sent to owner.
     */
    public function markSent(CashierShiftReport $report): CashierShiftReport
    {
        $report->update([
            'sent_to_owner' => true,
            'sent_at' => now(),
        ]);
        return $report->fresh();
    }

    private function buildSummaryEn(CashierPerformanceSnapshot $s, int $anomalyCount, int $badgeCount, RiskLevel $risk): string
    {
        $lines = [];
        $lines[] = "Shift Report — {$s->total_transactions} transactions, " . number_format((float) $s->total_revenue, 2) . " SAR revenue.";
        $lines[] = "Speed: {$s->items_per_minute} items/min, Avg basket: " . number_format((float) $s->avg_basket_size, 2) . " SAR.";

        if ((int) $s->void_count > 0) {
            $lines[] = "Voids: {$s->void_count} (" . number_format((float) $s->void_amount, 2) . " SAR).";
        }
        if ((int) $s->no_sale_count > 0) {
            $lines[] = "No-sale drawer opens: {$s->no_sale_count}.";
        }

        if ($anomalyCount > 0) {
            $lines[] = "⚠ {$anomalyCount} anomalies detected. Risk level: {$risk->value}.";
        } else {
            $lines[] = "✓ No anomalies detected. Risk level: {$risk->value}.";
        }

        if ($badgeCount > 0) {
            $lines[] = "🏆 {$badgeCount} badge(s) earned!";
        }

        return implode("\n", $lines);
    }

    private function buildSummaryAr(CashierPerformanceSnapshot $s, int $anomalyCount, int $badgeCount, RiskLevel $risk): string
    {
        $riskAr = match ($risk) {
            RiskLevel::Normal => 'طبيعي',
            RiskLevel::Elevated => 'مرتفع قليلاً',
            RiskLevel::High => 'مرتفع',
            RiskLevel::Critical => 'حرج',
        };

        $lines = [];
        $lines[] = "تقرير الوردية — {$s->total_transactions} معاملة، " . number_format((float) $s->total_revenue, 2) . " ريال إيرادات.";
        $lines[] = "السرعة: {$s->items_per_minute} عنصر/دقيقة، متوسط السلة: " . number_format((float) $s->avg_basket_size, 2) . " ريال.";

        if ((int) $s->void_count > 0) {
            $lines[] = "إلغاءات: {$s->void_count} (" . number_format((float) $s->void_amount, 2) . " ريال).";
        }
        if ((int) $s->no_sale_count > 0) {
            $lines[] = "فتح درج بدون بيع: {$s->no_sale_count}.";
        }

        if ($anomalyCount > 0) {
            $lines[] = "⚠ تم اكتشاف {$anomalyCount} حالة شاذة. مستوى المخاطر: {$riskAr}.";
        } else {
            $lines[] = "✓ لم يتم اكتشاف حالات شاذة. مستوى المخاطر: {$riskAr}.";
        }

        if ($badgeCount > 0) {
            $lines[] = "🏆 تم الحصول على {$badgeCount} شارة!";
        }

        return implode("\n", $lines);
    }
}
