<?php

namespace App\Domain\DeliveryIntegration\Jobs;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Services\MenuSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Spec Rule #3: When a product transitions to/from 0 stock, propagate the
 * availability change to every connected delivery platform within 60 seconds.
 *
 * Dispatched per (store, platform_config) pair from StockLevelObserver.
 */
class ToggleProductAvailabilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public string $configId,
        public string $productId,
        public bool $available,
    ) {
        $this->onQueue('delivery');
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(MenuSyncService $service): void
    {
        $config = DeliveryPlatformConfig::find($this->configId);

        if (! $config || ! $config->is_enabled) {
            return;
        }

        try {
            $service->toggleProductAvailability($config, $this->productId, $this->available);
        } catch (\Throwable $e) {
            Log::warning('delivery.toggle_availability.failed', [
                'config_id' => $this->configId,
                'product_id' => $this->productId,
                'available' => $this->available,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
