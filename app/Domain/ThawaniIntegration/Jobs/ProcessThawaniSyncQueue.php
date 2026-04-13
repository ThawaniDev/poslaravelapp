<?php

namespace App\Domain\ThawaniIntegration\Jobs;

use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Services\ThawaniService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessThawaniSyncQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        private ?string $storeId = null,
        private int $batchSize = 50,
    ) {}

    public function handle(ThawaniService $service): void
    {
        if ($this->storeId) {
            $this->processStore($service, $this->storeId);
            return;
        }

        // Process all connected stores
        $configs = ThawaniStoreConfig::where('is_connected', true)->get();

        foreach ($configs as $config) {
            try {
                $this->processStore($service, $config->store_id);
            } catch (\Exception $e) {
                Log::error('ProcessThawaniSyncQueue: Store processing failed', [
                    'store_id' => $config->store_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processStore(ThawaniService $service, string $storeId): void
    {
        $result = $service->processQueue($storeId, $this->batchSize);

        if ($result['processed'] > 0) {
            Log::info('ProcessThawaniSyncQueue: Processed sync queue', [
                'store_id' => $storeId,
                'processed' => $result['processed'],
                'success' => $result['success'],
                'failed' => $result['failed'],
            ]);
        }
    }
}
