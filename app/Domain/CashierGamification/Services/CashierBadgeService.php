<?php

namespace App\Domain\CashierGamification\Services;

use App\Domain\CashierGamification\Enums\CashierBadgeTrigger;
use App\Domain\CashierGamification\Enums\PerformancePeriod;
use App\Domain\CashierGamification\Models\CashierBadge;
use App\Domain\CashierGamification\Models\CashierBadgeAward;
use App\Domain\CashierGamification\Models\CashierPerformanceSnapshot;
use App\Domain\PosTerminal\Models\PosSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CashierBadgeService
{
    /**
     * Seed default badge definitions for a store.
     */
    public function seedDefaultBadges(string $storeId): int
    {
        $defaults = [
            [
                'slug' => 'sales_champion',
                'name_en' => 'Sales Champion',
                'name_ar' => 'بطل المبيعات',
                'description_en' => 'Highest daily revenue among all cashiers',
                'description_ar' => 'أعلى إيرادات يومية بين جميع الصرافين',
                'icon' => 'emoji_events',
                'color' => '#FFD700',
                'trigger_type' => CashierBadgeTrigger::SalesChampion->value,
                'trigger_threshold' => 0,
                'period' => PerformancePeriod::Daily->value,
            ],
            [
                'slug' => 'speed_star',
                'name_en' => 'Speed Star',
                'name_ar' => 'نجم السرعة',
                'description_en' => 'Highest items per minute in a shift',
                'description_ar' => 'أعلى عدد عناصر في الدقيقة خلال الوردية',
                'icon' => 'speed',
                'color' => '#FF6B35',
                'trigger_type' => CashierBadgeTrigger::SpeedStar->value,
                'trigger_threshold' => 0,
                'period' => PerformancePeriod::Daily->value,
            ],
            [
                'slug' => 'consistency_king',
                'name_en' => 'Consistency King',
                'name_ar' => 'ملك الثبات',
                'description_en' => 'Lowest void rate over the week',
                'description_ar' => 'أقل معدل إلغاء خلال الأسبوع',
                'icon' => 'verified',
                'color' => '#4CAF50',
                'trigger_type' => CashierBadgeTrigger::ConsistencyKing->value,
                'trigger_threshold' => 0,
                'period' => PerformancePeriod::Weekly->value,
            ],
            [
                'slug' => 'upsell_master',
                'name_en' => 'Upsell Master',
                'name_ar' => 'خبير البيع الإضافي',
                'description_en' => 'Highest upsell rate among cashiers',
                'description_ar' => 'أعلى معدل بيع إضافي بين الصرافين',
                'icon' => 'trending_up',
                'color' => '#2196F3',
                'trigger_type' => CashierBadgeTrigger::UpsellMaster->value,
                'trigger_threshold' => 0,
                'period' => PerformancePeriod::Daily->value,
            ],
            [
                'slug' => 'early_bird',
                'name_en' => 'Early Bird',
                'name_ar' => 'الطائر المبكر',
                'description_en' => 'First cashier to open a register today',
                'description_ar' => 'أول صراف يفتح السجل اليوم',
                'icon' => 'wb_sunny',
                'color' => '#FF9800',
                'trigger_type' => CashierBadgeTrigger::EarlyBird->value,
                'trigger_threshold' => 0,
                'period' => PerformancePeriod::Daily->value,
            ],
            [
                'slug' => 'marathon_runner',
                'name_en' => 'Marathon Runner',
                'name_ar' => 'عداء الماراثون',
                'description_en' => 'Most transactions processed in a shift',
                'description_ar' => 'أكثر معاملات تمت في وردية واحدة',
                'icon' => 'directions_run',
                'color' => '#9C27B0',
                'trigger_type' => CashierBadgeTrigger::MarathonRunner->value,
                'trigger_threshold' => 0,
                'period' => PerformancePeriod::Daily->value,
            ],
            [
                'slug' => 'zero_void',
                'name_en' => 'Zero Void',
                'name_ar' => 'صفر إلغاء',
                'description_en' => 'No voids in the entire shift — perfect accuracy',
                'description_ar' => 'لا إلغاءات في الوردية بأكملها — دقة مثالية',
                'icon' => 'check_circle',
                'color' => '#00BCD4',
                'trigger_type' => CashierBadgeTrigger::ZeroVoid->value,
                'trigger_threshold' => 0,
                'period' => PerformancePeriod::Shift->value,
            ],
            [
                'slug' => 'customer_favorite',
                'name_en' => 'Customer Favorite',
                'name_ar' => 'المفضل لدى العملاء',
                'description_en' => 'Highest average basket size among cashiers',
                'description_ar' => 'أعلى متوسط حجم سلة بين الصرافين',
                'icon' => 'favorite',
                'color' => '#E91E63',
                'trigger_type' => CashierBadgeTrigger::CustomerFavorite->value,
                'trigger_threshold' => 0,
                'period' => PerformancePeriod::Daily->value,
            ],
        ];

        $count = 0;
        foreach ($defaults as $badge) {
            CashierBadge::firstOrCreate(
                ['store_id' => $storeId, 'slug' => $badge['slug']],
                array_merge($badge, ['store_id' => $storeId, 'sort_order' => $count])
            );
            $count++;
        }

        return $count;
    }

    /**
     * Evaluate and award badges based on a snapshot.
     *
     * @return CashierBadgeAward[]
     */
    public function evaluateAndAward(string $storeId, CashierPerformanceSnapshot $snapshot): array
    {
        $badges = CashierBadge::where('store_id', $storeId)
            ->where('is_active', true)
            ->get();

        $date = $snapshot->date;
        $cashierId = $snapshot->cashier_id;
        $awarded = [];

        // Get all daily snapshots for the same date to compare
        $allSnapshots = CashierPerformanceSnapshot::where('store_id', $storeId)
            ->whereDate('date', $date)
            ->where('period_type', PerformancePeriod::Daily->value)
            ->get();

        foreach ($badges as $badge) {
            $trigger = $badge->trigger_type;
            $earned = false;
            $metricValue = 0;

            switch ($trigger) {
                case CashierBadgeTrigger::SalesChampion:
                    $max = $allSnapshots->sortByDesc('total_revenue')->first();
                    if ($max && $max->cashier_id === $cashierId && $allSnapshots->count() > 1) {
                        $earned = true;
                        $metricValue = (float) $snapshot->total_revenue;
                    }
                    break;

                case CashierBadgeTrigger::SpeedStar:
                    $max = $allSnapshots->sortByDesc('items_per_minute')->first();
                    if ($max && $max->cashier_id === $cashierId && $allSnapshots->count() > 1) {
                        $earned = true;
                        $metricValue = (float) $snapshot->items_per_minute;
                    }
                    break;

                case CashierBadgeTrigger::ConsistencyKing:
                    // Weekly badge — use snapshot date to determine week boundaries
                    if ($badge->period === PerformancePeriod::Weekly) {
                        $snapshotDate = \Illuminate\Support\Carbon::parse($date);
                        $weekStart = $snapshotDate->copy()->startOfWeek()->toDateString();
                        $weekEnd = $snapshotDate->copy()->endOfWeek()->toDateString();

                        // Get all cashiers' weekly average void rates (minimum 3 days for reliability)
                        $weeklyRankings = CashierPerformanceSnapshot::where('store_id', $storeId)
                            ->where('period_type', PerformancePeriod::Daily->value)
                            ->whereBetween('date', [$weekStart, $weekEnd])
                            ->selectRaw('cashier_id, AVG(void_rate) as avg_void, COUNT(*) as day_count')
                            ->groupBy('cashier_id')
                            ->having('day_count', '>=', 3)
                            ->orderBy('avg_void', 'asc')
                            ->get();

                        if ($weeklyRankings->count() > 1) {
                            $best = $weeklyRankings->first();
                            if ($best && $best->cashier_id === $cashierId) {
                                $earned = true;
                                $metricValue = round((float) $best->avg_void, 4);
                            }
                        }
                    }
                    break;

                case CashierBadgeTrigger::UpsellMaster:
                    $max = $allSnapshots->sortByDesc('upsell_rate')->first();
                    if ($max && $max->cashier_id === $cashierId && (float) $snapshot->upsell_rate > 0 && $allSnapshots->count() > 1) {
                        $earned = true;
                        $metricValue = (float) $snapshot->upsell_rate;
                    }
                    break;

                case CashierBadgeTrigger::EarlyBird:
                    // First cashier to open session today
                    $firstSession = PosSession::where('store_id', $storeId)
                        ->whereDate('opened_at', $date)
                        ->orderBy('opened_at', 'asc')
                        ->first();
                    if ($firstSession && $firstSession->cashier_id === $cashierId) {
                        $earned = true;
                        $metricValue = 1;
                    }
                    break;

                case CashierBadgeTrigger::MarathonRunner:
                    $max = $allSnapshots->sortByDesc('total_transactions')->first();
                    if ($max && $max->cashier_id === $cashierId && $allSnapshots->count() > 1) {
                        $earned = true;
                        $metricValue = (float) $snapshot->total_transactions;
                    }
                    break;

                case CashierBadgeTrigger::ZeroVoid:
                    if ((int) $snapshot->void_count === 0 && (int) $snapshot->total_transactions > 0) {
                        $earned = true;
                        $metricValue = 0;
                    }
                    break;

                case CashierBadgeTrigger::CustomerFavorite:
                    $max = $allSnapshots->sortByDesc('avg_basket_size')->first();
                    if ($max && $max->cashier_id === $cashierId && $allSnapshots->count() > 1) {
                        $earned = true;
                        $metricValue = (float) $snapshot->avg_basket_size;
                    }
                    break;
            }

            if ($earned) {
                // Check if already awarded for this date + badge
                $existing = CashierBadgeAward::where('store_id', $storeId)
                    ->where('cashier_id', $cashierId)
                    ->where('badge_id', $badge->id)
                    ->where('earned_date', $date)
                    ->first();

                if (!$existing) {
                    $awarded[] = CashierBadgeAward::create([
                        'store_id' => $storeId,
                        'cashier_id' => $cashierId,
                        'badge_id' => $badge->id,
                        'snapshot_id' => $snapshot->id,
                        'earned_date' => $date,
                        'period' => $badge->period->value,
                        'metric_value' => $metricValue,
                        'created_at' => now(),
                    ]);
                }
            }
        }

        return $awarded;
    }

    /**
     * List badges for a store.
     */
    public function listBadges(string $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return CashierBadge::where('store_id', $storeId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * List badge awards for a store with filters.
     */
    public function listAwards(string $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CashierBadgeAward::where('store_id', $storeId)
            ->with(['cashier:id,name,email', 'badge']);

        if (!empty($filters['cashier_id'])) {
            $query->where('cashier_id', $filters['cashier_id']);
        }
        if (!empty($filters['badge_id'])) {
            $query->where('badge_id', $filters['badge_id']);
        }

        $query->orderBy('earned_date', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Create a custom badge.
     */
    public function createBadge(string $storeId, array $data): CashierBadge
    {
        return CashierBadge::create(array_merge($data, ['store_id' => $storeId]));
    }

    /**
     * Update a badge.
     */
    public function updateBadge(CashierBadge $badge, array $data): CashierBadge
    {
        $badge->update($data);
        return $badge->fresh();
    }

    /**
     * Delete a badge.
     */
    public function deleteBadge(CashierBadge $badge): void
    {
        $badge->delete();
    }
}
