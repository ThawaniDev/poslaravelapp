<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class StoreHealthService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'store_health'; }

    public function calculateAll(?string $userId = null): ?array
    {
        $stores = DB::select("
            SELECT s.id, s.name, s.name_ar, o.name as org_name,
                   s.is_active, s.created_at,
                   (SELECT COUNT(*) FROM transactions t WHERE t.store_id = s.id AND t.created_at >= NOW() - INTERVAL '7 days') as txn_7d,
                   (SELECT COUNT(*) FROM transactions t WHERE t.store_id = s.id AND t.created_at >= NOW() - INTERVAL '30 days') as txn_30d,
                   (SELECT COUNT(*) FROM support_tickets st WHERE st.store_id = s.id AND st.status = 'open') as open_tickets,
                   (SELECT MAX(r.last_sync_at) FROM registers r WHERE r.store_id = s.id) as last_sync
            FROM stores s
            JOIN organizations o ON o.id = s.organization_id
            WHERE s.is_active = true
            ORDER BY txn_30d DESC
            LIMIT 200
        ");

        $context = [
            'stores' => json_encode($stores, JSON_UNESCAPED_UNICODE),
        ];

        // Platform-level call — use first store as placeholder
        $storeId = $stores[0]->id ?? 'platform';
        $orgId = 'platform';

        return $this->callAI($storeId, $orgId, $context, $userId, cacheTtlMinutes: 360);
    }
}
