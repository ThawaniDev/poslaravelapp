<?php

namespace App\Domain\DeliveryIntegration\Jobs;

use App\Domain\DeliveryIntegration\Enums\MenuSyncTrigger;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Services\MenuSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MenuSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly string $configId,
        private readonly array $products,
        private readonly MenuSyncTrigger $trigger = MenuSyncTrigger::Manual,
    ) {
        $this->queue = 'delivery';
    }

    public function handle(MenuSyncService $service): void
    {
        $config = DeliveryPlatformConfig::find($this->configId);
        if (! $config || ! $config->is_enabled) {
            return;
        }

        $products = $this->products;

        // If dispatched without an explicit catalog (e.g. by ProductMenuSyncObserver
        // or the hourly auto-sync command), load the active catalogue for the
        // store's organization at handle time.
        if (empty($products)) {
            $store = \App\Domain\Core\Models\Store::find($config->store_id);
            if ($store) {
                $products = \App\Domain\Catalog\Models\Product::query()
                    ->where('organization_id', $store->organization_id)
                    ->where('is_active', true)
                    ->get()
                    ->toArray();
            }
        }

        $service->syncMenu($config, $products, $this->trigger);
    }
}
