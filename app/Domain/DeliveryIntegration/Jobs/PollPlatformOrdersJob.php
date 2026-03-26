<?php

namespace App\Domain\DeliveryIntegration\Jobs;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Services\DeliveryAdapterFactory;
use App\Domain\DeliveryIntegration\Services\OrderIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollPlatformOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private readonly string $configId,
    ) {
        $this->queue = 'delivery';
    }

    public function handle(OrderIngestService $ingestService): void
    {
        $config = DeliveryPlatformConfig::find($this->configId);
        if (! $config || ! $config->is_enabled) {
            return;
        }

        try {
            $adapter = DeliveryAdapterFactory::make($config);
            $result = $adapter->fetchOrders($config->getCredentials());

            if (! ($result['success'] ?? false)) {
                Log::warning('Poll orders failed', [
                    'config_id' => $this->configId,
                    'platform' => $config->platform->value,
                    'error' => $result['message'] ?? 'Unknown',
                ]);

                return;
            }

            foreach ($result['orders'] ?? [] as $rawOrder) {
                $ingestService->ingestFromWebhook(
                    $config->store_id,
                    $config->platform->value,
                    $rawOrder,
                );
            }
        } catch (\Throwable $e) {
            Log::error('Poll orders exception', [
                'config_id' => $this->configId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
