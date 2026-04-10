<?php

namespace App\Domain\WameedAI\Listeners;

use App\Domain\WameedAI\Events\AIFeatureInvoked;
use App\Domain\WameedAI\Models\AIUsageLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogAIFeatureUsage implements ShouldQueue
{
    public function handle(AIFeatureInvoked $event): void
    {
        try {
            AIUsageLog::create([
                'store_id' => $event->storeId,
                'organization_id' => $event->organizationId,
                'user_id' => $event->userId,
                'feature_slug' => $event->featureSlug,
                'total_tokens' => $event->tokensUsed ?? 0,
                'estimated_cost_usd' => $event->costEstimate ?? 0.0,
                'latency_ms' => $event->processingTimeMs ?? 0,
                'status' => 'success',
                'response_cached' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error("WameedAI: Failed to log feature usage", [
                'feature' => $event->featureSlug,
                'store_id' => $event->storeId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
