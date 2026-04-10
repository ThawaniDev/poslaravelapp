<?php

namespace App\Domain\WameedAI\Jobs;

use App\Domain\WameedAI\Services\Features\SmartReorderService;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AISuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReorderSuggestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly ?string $storeId = null,
    ) {}

    public function handle(SmartReorderService $service): void
    {
        $configs = AIStoreFeatureConfig::query()
            ->whereHas('featureDefinition', fn ($q) => $q->where('slug', 'smart_reorder')->where('is_enabled', true))
            ->where('is_enabled', true)
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->with('featureDefinition')
            ->get();

        foreach ($configs as $config) {
            try {
                $result = $service->getSuggestions($config->store_id, $config->store->organization_id ?? '');
                if ($result && !empty($result['suggestions'])) {
                    foreach (array_slice($result['suggestions'], 0, 10) as $item) {
                        AISuggestion::create([
                            'store_id' => $config->store_id,
                            'feature_slug' => 'smart_reorder',
                            'suggestion_type' => 'reorder',
                            'title' => $item['product_name'] ?? $item['product'] ?? 'Reorder Suggestion',
                            'title_ar' => $item['product_name_ar'] ?? null,
                            'content_json' => $item,
                            'priority' => ($item['urgency'] ?? 'medium') === 'critical' ? 'high' : ($item['urgency'] ?? 'medium'),
                            'status' => 'pending',
                            'expires_at' => now()->addDays(7),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("WameedAI: Reorder job failed for store {$config->store_id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
