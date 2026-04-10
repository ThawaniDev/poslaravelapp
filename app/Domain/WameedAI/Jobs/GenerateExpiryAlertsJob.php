<?php

namespace App\Domain\WameedAI\Jobs;

use App\Domain\WameedAI\Services\Features\ExpiryManagerService;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AISuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateExpiryAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly ?string $storeId = null,
    ) {}

    public function handle(ExpiryManagerService $service): void
    {
        $configs = AIStoreFeatureConfig::query()
            ->whereHas('featureDefinition', fn ($q) => $q->where('slug', 'expiry_manager')->where('is_enabled', true))
            ->where('is_enabled', true)
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->with('featureDefinition')
            ->get();

        foreach ($configs as $config) {
            try {
                $result = $service->getExpiringProducts($config->store_id, $config->store->organization_id ?? '');
                if ($result && !empty($result['expiring_products'])) {
                    foreach (array_slice($result['expiring_products'], 0, 15) as $product) {
                        $daysLeft = $product['days_until_expiry'] ?? 30;
                        $priority = $daysLeft <= 3 ? 'critical' : ($daysLeft <= 7 ? 'high' : 'medium');

                        AISuggestion::create([
                            'store_id' => $config->store_id,
                            'feature_slug' => 'expiry_manager',
                            'suggestion_type' => 'alert',
                            'title' => ($product['product_name'] ?? 'Product') . ' - Expires in ' . $daysLeft . ' days',
                            'content_json' => $product,
                            'priority' => $priority,
                            'status' => 'pending',
                            'expires_at' => now()->addDays($daysLeft),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("WameedAI: Expiry alerts job failed for store {$config->store_id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
