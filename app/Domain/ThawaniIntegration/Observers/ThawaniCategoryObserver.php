<?php

namespace App\Domain\ThawaniIntegration\Observers;

use App\Domain\Catalog\Models\Category;
use App\Domain\ThawaniIntegration\Models\ThawaniCategoryMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncQueue;
use Illuminate\Support\Facades\Log;

class ThawaniCategoryObserver
{
    public function created(Category $category): void
    {
        $this->queueCategorySync($category, 'create');
    }

    public function updated(Category $category): void
    {
        $this->queueCategorySync($category, 'update');
    }

    public function deleted(Category $category): void
    {
        $this->queueCategorySync($category, 'delete');
    }

    private function queueCategorySync(Category $category, string $action): void
    {
        try {
            $configs = ThawaniStoreConfig::whereHas('store', function ($q) use ($category) {
                    $q->where('organization_id', $category->organization_id);
                })
                ->where('is_connected', true)
                ->where('auto_sync_products', true)
                ->get();

            foreach ($configs as $config) {
                if ($action !== 'create') {
                    $hasMapping = ThawaniCategoryMapping::where('store_id', $config->store_id)
                        ->where('category_id', $category->id)
                        ->exists();

                    if (!$hasMapping) continue;
                }

                $exists = ThawaniSyncQueue::where('store_id', $config->store_id)
                    ->where('entity_type', 'category')
                    ->where('entity_id', $category->id)
                    ->where('action', $action)
                    ->where('status', 'pending')
                    ->exists();

                if (!$exists) {
                    ThawaniSyncQueue::create([
                        'store_id' => $config->store_id,
                        'entity_type' => 'category',
                        'entity_id' => $category->id,
                        'action' => $action,
                        'payload' => $category->only(['name', 'name_ar', 'image_url']),
                        'status' => 'pending',
                        'scheduled_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('ThawaniCategoryObserver: Failed to queue sync', [
                'category_id' => $category->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
