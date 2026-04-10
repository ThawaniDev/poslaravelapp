<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SentimentAnalysisService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'sentiment_analysis'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $ticketMessages = DB::select("
            SELECT stm.message, stm.is_from_staff, st.subject, st.status, st.created_at
            FROM support_ticket_messages stm
            JOIN support_tickets st ON st.id = stm.support_ticket_id
            WHERE st.store_id = ? AND st.created_at >= NOW() - INTERVAL '30 days'
            ORDER BY st.created_at DESC
            LIMIT 100
        ", [$storeId]);

        $context = [
            'messages' => json_encode($ticketMessages, JSON_UNESCAPED_UNICODE),
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
