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
        $expiringProducts = DB::select("
            SELECT sb.id as batch_id, sb.product_id, p.name, p.name_ar, p.sell_price,
                   sb.expiry_date, sb.quantity as batch_qty,
                   EXTRACT(DAY FROM sb.expiry_date - NOW()) as days_until_expiry
            FROM stock_batches sb
            JOIN products p ON p.id = sb.product_id
            WHERE sb.store_id = ? AND sb.expiry_date IS NOT NULL
              AND sb.expiry_date <= NOW() + INTERVAL '{$daysAhead} days'
              AND sb.expiry_date > NOW()
              AND sb.quantity > 0
            ORDER BY sb.expiry_date ASC
            LIMIT 50
        ", [$storeId]);

        if (empty($expiringProducts)) {
            return ['alerts' => [], 'message' => 'No products nearing expiry'];
        }

        $context = [
            'expiring_products' => json_encode($expiringProducts, JSON_UNESCAPED_UNICODE),
            'days_ahead' => $daysAhead,
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
