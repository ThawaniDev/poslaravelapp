<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SentimentAnalysisService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'sentiment_analysis'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $ticketMessages = DB::select("
            SELECT stm.message_text as message, stm.sender_type, st.subject, st.status,
                   st.priority, st.created_at
            FROM support_ticket_messages stm
            JOIN support_tickets st ON st.id = stm.support_ticket_id
            WHERE st.store_id = ? AND st.created_at >= NOW() - INTERVAL '30 days'
            ORDER BY st.created_at DESC
            LIMIT 100
        ", [$storeId]);

        if (empty($ticketMessages)) {
            return ['overall_sentiment' => 'neutral', 'score' => 50, 'themes' => [], 'message' => 'No feedback data available'];
        }

        $returnReasons = DB::select("
            SELECT t.notes, COUNT(*) as count
            FROM transactions t
            WHERE t.store_id = ? AND t.type = 'return'
              AND t.created_at >= NOW() - INTERVAL '30 days'
              AND t.notes IS NOT NULL AND t.notes != ''
            GROUP BY t.notes
            ORDER BY count DESC LIMIT 20
        ", [$storeId]);

        $ticketStats = DB::selectOne("
            SELECT COUNT(*) as total_tickets,
                   SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
                   SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                   AVG(CASE WHEN resolved_at IS NOT NULL THEN EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600 END) as avg_resolution_hours
            FROM support_tickets
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '30 days'
        ", [$storeId]);

        $context = [
            'messages' => json_encode($ticketMessages, JSON_UNESCAPED_UNICODE),
            'return_reasons' => json_encode($returnReasons, JSON_UNESCAPED_UNICODE),
            'ticket_stats' => json_encode($ticketStats, JSON_UNESCAPED_UNICODE),
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
