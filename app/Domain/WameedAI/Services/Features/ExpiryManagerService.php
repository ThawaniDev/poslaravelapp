<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class ExpiryManagerService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'expiry_manager';
    }

    public function getAlerts(string $storeId, string $organizationId, int $daysAhead = 30, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $expiringProducts = DB::select("
            SELECT sb.id as batch_id, sb.product_id, p.name, p.name_ar, p.sell_price, p.cost_price,
                   c.name as category,
                   sb.expiry_date, sb.quantity as batch_qty, sb.batch_number,
                   EXTRACT(DAY FROM sb.expiry_date - NOW()) as days_until_expiry,
                   COALESCE(velocity.avg_daily_sales, 0) as avg_daily_sales,
                   CASE WHEN COALESCE(velocity.avg_daily_sales, 0) > 0
                        THEN ROUND(sb.quantity / velocity.avg_daily_sales)
                        ELSE 9999 END as days_to_sell_at_current_rate
            FROM stock_batches sb
            JOIN products p ON p.id = sb.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN LATERAL (
                SELECT SUM(ti.quantity)::DECIMAL / 30 as avg_daily_sales
                FROM transaction_items ti
                JOIN transactions t ON t.id = ti.transaction_id
                WHERE ti.product_id = sb.product_id AND t.store_id = ?
                  AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
            ) velocity ON true
            WHERE sb.store_id = ? AND sb.expiry_date IS NOT NULL
              AND sb.expiry_date <= NOW() + INTERVAL '{$daysAhead} days'
              AND sb.expiry_date > NOW()
              AND sb.quantity > 0
            ORDER BY sb.expiry_date ASC
            LIMIT 50
        ", [$storeId, $storeId]);

        if (empty($expiringProducts)) {
            return ['alerts' => [], 'message' => 'No products nearing expiry'];
        }

        $alreadyExpired = DB::select("
            SELECT p.name, p.name_ar, sb.quantity, sb.expiry_date,
                   (sb.quantity * COALESCE(p.cost_price, 0)) as waste_value
            FROM stock_batches sb
            JOIN products p ON p.id = sb.product_id
            WHERE sb.store_id = ? AND sb.expiry_date < NOW() AND sb.quantity > 0
            ORDER BY sb.expiry_date ASC LIMIT 20
        ", [$storeId]);

        $totalExpiringValue = array_sum(array_map(fn ($p) => (float) $p->batch_qty * (float) $p->sell_price, $expiringProducts));

        $context = [
            'expiring_stock' => json_encode($expiringProducts, JSON_UNESCAPED_UNICODE),
            'already_expired' => json_encode($alreadyExpired, JSON_UNESCAPED_UNICODE),
            'total_expiring_value' => number_format($totalExpiringValue, 2),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
