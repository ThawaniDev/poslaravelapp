<?php

namespace App\Domain\WameedAI\Jobs;

use App\Domain\WameedAI\Services\Features\RevenueAnomalyService;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AISuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectAnomaliesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly ?string $storeId = null,
    ) {}

    public function handle(RevenueAnomalyService $service): void
    {
        $configs = AIStoreFeatureConfig::query()
            ->whereHas('featureDefinition', fn ($q) => $q->where('slug', 'revenue_anomaly')->where('is_enabled', true))
            ->where('is_enabled', true)
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->with('featureDefinition')
            ->get();

        foreach ($configs as $config) {
            try {
                $result = $service->detectAnomalies($config->store_id, $config->store->organization_id ?? '');
                if ($result && !empty($result['anomalies'])) {
                    foreach ($result['anomalies'] as $anomaly) {
                        AISuggestion::create([
                            'store_id' => $config->store_id,
                            'feature_slug' => 'revenue_anomaly',
                            'suggestion_type' => 'alert',
                            'title' => $anomaly['title'] ?? 'Revenue Anomaly Detected',
                            'content_json' => $anomaly,
                            'priority' => ($anomaly['severity'] ?? 'medium') === 'high' ? 'high' : 'medium',
                            'status' => 'pending',
                            'expires_at' => now()->addDays(5),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("WameedAI: Anomaly detection job failed for store {$config->store_id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
