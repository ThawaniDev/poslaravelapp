<?php

namespace App\Domain\WameedAI\Jobs;

use App\Domain\WameedAI\Services\Features\CashierErrorService;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AISuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectCashierErrorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly ?string $storeId = null,
    ) {}

    public function handle(CashierErrorService $service): void
    {
        $configs = AIStoreFeatureConfig::query()
            ->whereHas('featureDefinition', fn ($q) => $q->where('slug', 'cashier_error_detection')->where('is_enabled', true))
            ->where('is_enabled', true)
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->with('featureDefinition')
            ->get();

        foreach ($configs as $config) {
            try {
                $result = $service->detectErrors($config->store_id, $config->store->organization_id ?? '');
                if ($result && !empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        AISuggestion::create([
                            'store_id' => $config->store_id,
                            'feature_slug' => 'cashier_error_detection',
                            'suggestion_type' => 'alert',
                            'title' => $error['description'] ?? 'Cashier Error Detected',
                            'content_json' => $error,
                            'priority' => ($error['severity'] ?? 'medium') === 'high' ? 'high' : 'medium',
                            'status' => 'pending',
                            'expires_at' => now()->addDays(3),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("WameedAI: Cashier error detection failed for store {$config->store_id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
