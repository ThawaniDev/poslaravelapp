<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class CustomerSegmentationService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'customer_segmentation'; }

    public function segment(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $customers = DB::select("
            SELECT c.id, c.name, c.phone, c.total_spend, c.visit_count,
                   c.last_visit_at, c.created_at,
                   EXTRACT(DAY FROM NOW() - c.last_visit_at) as days_since_last_visit
            FROM customers c
            WHERE c.organization_id = ?
              AND c.visit_count > 0
            ORDER BY c.total_spend DESC
            LIMIT 500
        ", [$organizationId]);

        $context = [
            'customers' => json_encode($customers, JSON_UNESCAPED_UNICODE),
            'total_customers' => count($customers),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
