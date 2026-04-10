<?php

namespace App\Domain\WameedAI\Jobs;

use App\Domain\WameedAI\Services\Features\EfficiencyScoreService;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AISuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateEfficiencyScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly ?string $storeId = null,
    ) {}

    public function handle(EfficiencyScoreService $service): void
    {
        $configs = AIStoreFeatureConfig::query()
            ->whereHas('featureDefinition', fn ($q) => $q->where('slug', 'efficiency_score')->where('is_enabled', true))
            ->where('is_enabled', true)
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->with('featureDefinition')
            ->get();

        foreach ($configs as $config) {
            try {
                $result = $service->calculateScore($config->store_id, $config->store->organization_id ?? '');
                if ($result) {
                    AISuggestion::create([
                        'store_id' => $config->store_id,
                        'feature_slug' => 'efficiency_score',
                        'suggestion_type' => 'insight',
                        'title' => 'Efficiency Score: ' . ($result['overall_score'] ?? 'N/A') . '/100',
                        'content_json' => $result,
                        'priority' => ($result['overall_score'] ?? 50) < 50 ? 'high' : 'medium',
                        'status' => 'pending',
                        'expires_at' => now()->addDays(1),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error("WameedAI: Efficiency score calculation failed for store {$config->store_id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
