<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class StoreHealthService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'store_health'; }

    public function calculateAll(?string $userId = null): ?array
    {
        $stores = DB::select("
            SELECT s.name, s.name_ar, o.name as org_name,
                   s.is_active, s.created_at,
                   (SELECT COUNT(*) FROM transactions t WHERE t.store_id = s.id AND t.created_at >= NOW() - INTERVAL '7 days' AND t.status = 'completed') as txn_7d,
                   (SELECT COALESCE(SUM(total_amount), 0) FROM transactions t WHERE t.store_id = s.id AND t.created_at >= NOW() - INTERVAL '7 days' AND t.status = 'completed') as revenue_7d,
                   (SELECT COUNT(*) FROM transactions t WHERE t.store_id = s.id AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed') as txn_30d,
                   (SELECT COALESCE(SUM(total_amount), 0) FROM transactions t WHERE t.store_id = s.id AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed') as revenue_30d,
                   (SELECT COUNT(*) FROM products p WHERE p.organization_id = s.organization_id AND p.is_active = true) as active_products,
                   (SELECT COUNT(*) FROM users u WHERE u.store_id = s.id AND u.is_active = true) as active_staff,
                   (SELECT MAX(t.created_at) FROM transactions t WHERE t.store_id = s.id) as last_transaction
            FROM stores s
            JOIN organizations o ON o.id = s.organization_id
            WHERE s.is_active = true
            ORDER BY txn_30d DESC
            LIMIT 200
        ");

        if (empty($stores)) {
            return ['store_scores' => [], 'message' => 'No active stores found'];
        }

        $context = [
            'stores' => json_encode($stores, JSON_UNESCAPED_UNICODE),
        ];

        $storeId = $stores[0]->id ?? 'platform';

        return $this->callAI($storeId, 'platform', $context, $userId, cacheTtlMinutes: 360);
    }
}
