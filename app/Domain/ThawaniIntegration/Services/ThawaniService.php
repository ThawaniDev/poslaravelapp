<?php

namespace App\Domain\ThawaniIntegration\Services;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\ThawaniIntegration\Models\ThawaniCategoryMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniColumnMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniOrderMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniSettlement;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncLog;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThawaniService
{
    // ==================== Sync Logging ====================

    private function logSync(
        string $storeId,
        string $entityType,
        ?string $entityId,
        string $action,
        string $direction,
        string $status,
        ?array $requestData = null,
        ?array $responseData = null,
        ?string $errorMessage = null,
        ?int $httpStatusCode = null,
    ): void {
        try {
            ThawaniSyncLog::create([
                'store_id' => $storeId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'direction' => $direction,
                'status' => $status,
                'request_data' => $requestData,
                'response_data' => $responseData,
                'error_message' => $errorMessage,
                'http_status_code' => $httpStatusCode,
                'completed_at' => $status !== 'pending' ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ThawaniService: Failed to write sync log', ['error' => $e->getMessage()]);
        }
    }

    // ==================== Config Management ====================

    public function getConfig(string $storeId): ?ThawaniStoreConfig
    {
        return ThawaniStoreConfig::where('store_id', $storeId)->first();
    }

    public function saveConfig(string $storeId, array $data): ThawaniStoreConfig
    {
        $attributes = [
            'is_connected' => $data['is_connected'] ?? false,
            'auto_sync_products' => $data['auto_sync_products'] ?? false,
            'auto_sync_inventory' => $data['auto_sync_inventory'] ?? false,
            'auto_accept_orders' => $data['auto_accept_orders'] ?? false,
            'operating_hours_json' => $data['operating_hours'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'connected_at' => ($data['is_connected'] ?? false) ? now() : null,
        ];

        if (!empty($data['thawani_store_id'])) {
            $attributes['thawani_store_id'] = $data['thawani_store_id'];
        }
        if (!empty($data['marketplace_url'])) {
            $attributes['marketplace_url'] = $data['marketplace_url'];
        }
        if (!empty($data['api_key'])) {
            $attributes['api_key'] = $data['api_key'];
        }
        if (!empty($data['api_secret'])) {
            $attributes['api_secret'] = $data['api_secret'];
        }

        $existing = ThawaniStoreConfig::where('store_id', $storeId)->first();

        if ($existing) {
            $existing->update($attributes);
            return $existing->fresh();
        }

        return ThawaniStoreConfig::create(array_merge(
            ['store_id' => $storeId, 'thawani_store_id' => $data['thawani_store_id'] ?? $storeId],
            $attributes,
        ));
    }

    public function disconnect(string $storeId): bool
    {
        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();
        if (!$config) {
            return false;
        }

        $config->update(['is_connected' => false, 'connected_at' => null]);
        return true;
    }

    // ==================== Connection Test ====================

    public function testConnection(string $storeId): array
    {
        $client = new ThawaniApiClient($storeId);

        if (!$client->isConfigured()) {
            return ['success' => false, 'message' => 'API credentials not configured'];
        }

        $config = $this->getConfig($storeId);
        $store = $config?->store;

        $result = $client->post('connect', [
            'wameed_store_id' => $storeId,
            'wameed_store_name' => $store?->name ?? 'Wameed POS Store',
            'wameed_store_name_ar' => $store?->name_ar ?? null,
        ]);

        if ($result['success']) {
            $config = ThawaniStoreConfig::where('store_id', $storeId)->first();
            if ($config && isset($result['data']['thawani_store_id'])) {
                $config->update([
                    'thawani_store_id' => $result['data']['thawani_store_id'],
                    'is_connected' => true,
                    'connected_at' => now(),
                ]);
            }
        }

        $this->logSync(
            $storeId, 'connection', null, 'test_connection', 'outgoing',
            $result['success'] ? 'success' : 'failed',
            ['wameed_store_id' => $storeId],
            $result['data'] ?? null,
            $result['success'] ? null : ($result['message'] ?? 'Connection failed'),
            $result['http_status'] ?? null,
        );

        return $result;
    }

    // ==================== Category Sync ====================

    public function pushCategoriesToThawani(string $storeId): array
    {
        $client = new ThawaniApiClient($storeId);
        $config = $this->getConfig($storeId);

        if (!$config?->is_connected) {
            return ['success' => false, 'message' => 'Not connected to Thawani'];
        }

        $categories = Category::where('organization_id', $config->store?->organization_id)
            ->where('is_active', true)
            ->get();

        $columnMappings = ThawaniColumnMapping::getMappingsForEntity('category');
        $existingMappings = ThawaniCategoryMapping::where('store_id', $storeId)
            ->pluck('thawani_category_id', 'category_id')
            ->toArray();

        $mappedCategories = [];
        foreach ($categories as $category) {
            $mapped = $this->applyColumnMappingsOutgoing($category->toArray(), $columnMappings);
            $mapped['wameed_category_id'] = $category->id;
            $mapped['name'] = $category->name;
            $mapped['name_ar'] = $category->name_ar ?? $category->name;
            $mapped['action'] = isset($existingMappings[$category->id]) ? 'update' : 'create';
            $mappedCategories[] = $mapped;
        }

        $result = $client->post('categories/sync', [
            'categories' => $mappedCategories,
        ]);

        if ($result['success'] && isset($result['data']['results'])) {
            foreach ($result['data']['results'] as $syncResult) {
                if (($syncResult['status'] ?? '') !== 'success') continue;
                ThawaniCategoryMapping::updateOrCreate(
                    ['store_id' => $storeId, 'category_id' => $syncResult['wameed_category_id'] ?? null],
                    [
                        'thawani_category_id' => $syncResult['thawani_category_id'] ?? null,
                        'sync_status' => 'synced',
                        'sync_direction' => 'outgoing',
                        'sync_error' => null,
                        'last_synced_at' => now(),
                    ]
                );
            }
        }

        $this->logSync(
            $storeId, 'category', null, 'push_batch', 'outgoing',
            $result['success'] ? 'success' : 'failed',
            null,
            ['count' => count($mappedCategories)],
            $result['success'] ? null : ($result['message'] ?? 'Push categories failed'),
            $result['http_status'] ?? null,
        );

        return $result;
    }

    public function pullCategoriesFromThawani(string $storeId): array
    {
        $client = new ThawaniApiClient($storeId);
        $config = $this->getConfig($storeId);

        if (!$config?->is_connected) {
            return ['success' => false, 'message' => 'Not connected to Thawani'];
        }

        $result = $client->get('categories');

        if ($result['success'] && isset($result['data'])) {
            $columnMappings = ThawaniColumnMapping::getMappingsForEntity('category');
            $categoriesData = is_array($result['data']) ? $result['data'] : [];

            foreach ($categoriesData as $thawaniCategory) {
                if (!isset($thawaniCategory['thawani_category_id'])) continue;

                $mapped = $this->applyColumnMappingsIncoming($thawaniCategory, $columnMappings);

                $existing = ThawaniCategoryMapping::where('store_id', $storeId)
                    ->where('thawani_category_id', $thawaniCategory['thawani_category_id'])
                    ->first();

                if ($existing && $existing->category_id) {
                    $category = Category::find($existing->category_id);
                    if ($category) {
                        $category->update(array_filter($mapped));
                    }
                } else {
                    $category = Category::create(array_merge($mapped, [
                        'organization_id' => $config->store?->organization_id,
                        'is_active' => true,
                    ]));

                    ThawaniCategoryMapping::updateOrCreate(
                        ['store_id' => $storeId, 'thawani_category_id' => $thawaniCategory['thawani_category_id']],
                        [
                            'category_id' => $category->id,
                            'sync_status' => 'synced',
                            'sync_direction' => 'incoming',
                            'last_synced_at' => now(),
                        ]
                    );
                }
            }
        }

        $categoriesData = $result['success'] && isset($result['data'])
            ? (is_array($result['data']) ? $result['data'] : [])
            : [];

        $this->logSync(
            $storeId, 'category', null, 'pull', 'incoming',
            $result['success'] ? 'success' : 'failed',
            null,
            ['count' => count($categoriesData)],
            $result['success'] ? null : ($result['message'] ?? 'Pull categories failed'),
            $result['http_status'] ?? null,
        );

        return $result;
    }

    // ==================== Product Sync ====================

    public function pushProductsToThawani(string $storeId): array
    {
        $client = new ThawaniApiClient($storeId);
        $config = $this->getConfig($storeId);

        if (!$config?->is_connected) {
            return ['success' => false, 'message' => 'Not connected to Thawani'];
        }

        $products = Product::where('organization_id', $config->store?->organization_id)
            ->where('is_active', true)
            ->get();

        $columnMappings = ThawaniColumnMapping::getMappingsForEntity('product');
        $categoryMappings = ThawaniCategoryMapping::where('store_id', $storeId)
            ->pluck('thawani_category_id', 'category_id')
            ->toArray();
        $existingProductMappings = ThawaniProductMapping::where('store_id', $storeId)
            ->pluck('thawani_product_id', 'product_id')
            ->toArray();

        $mappedProducts = [];
        foreach ($products as $product) {
            $mapped = $this->applyColumnMappingsOutgoing($product->toArray(), $columnMappings);
            $mapped['wameed_product_id'] = $product->id;
            $mapped['name'] = $product->name;
            $mapped['name_ar'] = $product->name_ar ?? $product->name;
            $mapped['description'] = $product->description ?? '';
            $mapped['description_ar'] = $product->description_ar ?? '';
            $mapped['price'] = (float) ($product->sell_price ?? $product->price ?? 0);
            $mapped['offer_price'] = $product->offer_price ? (float) $product->offer_price : null;
            $mapped['quantity'] = $product->quantity ?? 0;
            $mapped['barcode'] = $product->barcode;
            $mapped['is_active'] = (bool) ($product->is_active ?? true);
            $mapped['action'] = isset($existingProductMappings[$product->id]) ? 'update' : 'create';
            if (isset($product->category_id) && isset($categoryMappings[$product->category_id])) {
                $mapped['wameed_category_id'] = $product->category_id;
            }
            $mappedProducts[] = $mapped;
        }

        $result = $client->post('products/sync', [
            'products' => $mappedProducts,
        ]);

        if ($result['success'] && isset($result['data']['results'])) {
            foreach ($result['data']['results'] as $syncResult) {
                if (($syncResult['status'] ?? '') !== 'success') continue;
                ThawaniProductMapping::updateOrCreate(
                    ['store_id' => $storeId, 'product_id' => $syncResult['wameed_product_id'] ?? null],
                    [
                        'thawani_product_id' => $syncResult['thawani_product_id'] ?? null,
                        'is_published' => true,
                        'last_synced_at' => now(),
                    ]
                );
            }
        }

        $this->logSync(
            $storeId, 'product', null, 'push_batch', 'outgoing',
            $result['success'] ? 'success' : 'failed',
            ['count' => count($mappedProducts)],
            $result['data'] ?? null,
            $result['success'] ? null : ($result['message'] ?? 'Push products failed'),
            $result['http_status'] ?? null,
        );

        return $result;
    }

    public function pullProductsFromThawani(string $storeId): array
    {
        $client = new ThawaniApiClient($storeId);
        $config = $this->getConfig($storeId);

        if (!$config?->is_connected) {
            return ['success' => false, 'message' => 'Not connected to Thawani'];
        }

        $result = $client->get('products');

        if ($result['success'] && isset($result['data'])) {
            $columnMappings = ThawaniColumnMapping::getMappingsForEntity('product');
            $categoryMappings = ThawaniCategoryMapping::where('store_id', $storeId)
                ->pluck('category_id', 'thawani_category_id')
                ->toArray();

            // Products response is paginated: {data: [...], pagination: {...}}
            $productsData = isset($result['data']['data']) && is_array($result['data']['data'])
                ? $result['data']['data']
                : (is_array($result['data']) ? $result['data'] : []);

            foreach ($productsData as $thawaniProduct) {
                if (!isset($thawaniProduct['thawani_product_id'])) continue;

                $mapped = $this->applyColumnMappingsIncoming($thawaniProduct, $columnMappings);

                // Map category
                if (isset($thawaniProduct['store_category_id']) && isset($categoryMappings[$thawaniProduct['store_category_id']])) {
                    $mapped['category_id'] = $categoryMappings[$thawaniProduct['store_category_id']];
                }

                $existing = ThawaniProductMapping::where('store_id', $storeId)
                    ->where('thawani_product_id', $thawaniProduct['thawani_product_id'])
                    ->first();

                if ($existing && $existing->product_id) {
                    $product = Product::find($existing->product_id);
                    if ($product) {
                        $product->update(array_filter($mapped));
                    }
                } else {
                    $product = Product::create(array_merge($mapped, [
                        'organization_id' => $config->store?->organization_id,
                        'is_active' => true,
                    ]));

                    ThawaniProductMapping::updateOrCreate(
                        ['store_id' => $storeId, 'thawani_product_id' => $thawaniProduct['thawani_product_id']],
                        [
                            'product_id' => $product->id,
                            'is_published' => true,
                            'last_synced_at' => now(),
                        ]
                    );
                }
            }
        }

        $pulledCount = isset($result['data']['data']) && is_array($result['data']['data'])
            ? count($result['data']['data'])
            : (is_array($result['data'] ?? null) ? count($result['data']) : 0);

        $this->logSync(
            $storeId, 'product', null, 'pull', 'incoming',
            $result['success'] ? 'success' : 'failed',
            null,
            ['count' => $pulledCount],
            $result['success'] ? null : ($result['message'] ?? 'Pull products failed'),
            $result['http_status'] ?? null,
        );

        return $result;
    }

    // ==================== Sync Queue ====================

    public function queueSync(string $storeId, string $entityType, string $entityId, string $action, ?array $payload = null): ThawaniSyncQueue
    {
        return ThawaniSyncQueue::create([
            'store_id' => $storeId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'payload' => $payload,
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);
    }

    public function processQueue(string $storeId, int $batchSize = 50): array
    {
        $client = new ThawaniApiClient($storeId);
        $config = $this->getConfig($storeId);

        if (!$config?->is_connected || !$client->isConfigured()) {
            return ['processed' => 0, 'success' => 0, 'failed' => 0, 'reason' => 'Not connected'];
        }

        $items = ThawaniSyncQueue::pending()
            ->forStore($storeId)
            ->orderBy('scheduled_at')
            ->limit($batchSize)
            ->get();

        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];

        foreach ($items as $item) {
            $item->markProcessing();
            $stats['processed']++;

            try {
                $result = $this->processSyncItem($client, $item, $storeId);

                if ($result) {
                    $item->markCompleted();
                    $stats['success']++;
                } else {
                    $item->markFailed('Sync returned false');
                    $stats['failed']++;
                }
            } catch (\Exception $e) {
                $item->markFailed($e->getMessage());
                $stats['failed']++;
                Log::error('ThawaniService: Queue item failed', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function processSyncItem(ThawaniApiClient $client, ThawaniSyncQueue $item, string $storeId): bool
    {
        $columnMappings = ThawaniColumnMapping::getMappingsForEntity($item->entity_type);

        switch ($item->entity_type) {
            case 'product':
                return $this->syncSingleProduct($client, $item, $columnMappings, $storeId);
            case 'category':
                return $this->syncSingleCategory($client, $item, $columnMappings, $storeId);
            default:
                return false;
        }
    }

    private function syncSingleProduct(ThawaniApiClient $client, ThawaniSyncQueue $item, array $columnMappings, string $storeId): bool
    {
        // For delete actions, the product may already be soft-deleted
        $product = Product::withTrashed()->find($item->entity_id);

        // Handle delete action — product may not exist at all
        if ($item->action === 'delete') {
            $existingMapping = ThawaniProductMapping::where('store_id', $storeId)
                ->where('product_id', $item->entity_id)
                ->first();

            if (!$existingMapping) {
                // No mapping exists, nothing to delete on Thawani side
                return true;
            }

            $productPayload = [
                'wameed_product_id' => $item->entity_id,
                'name' => $product->name ?? ($item->payload['name'] ?? 'deleted'),
                'action' => 'delete',
            ];

            $result = $client->post('products/sync', [
                'products' => [$productPayload],
            ]);

            if ($result['success']) {
                $existingMapping->delete();
            }

            $this->logSync(
                $storeId, 'product', $item->entity_id, 'delete', 'outgoing',
                $result['success'] ? 'success' : 'failed',
                ['product_id' => $item->entity_id, 'action' => 'delete'],
                $result['data'] ?? null,
                $result['success'] ? null : ($result['message'] ?? 'Product delete sync failed'),
                $result['http_status'] ?? null,
            );

            return $result['success'];
        }

        if (!$product) return false;

        $mapped = $this->applyColumnMappingsOutgoing($product->toArray(), $columnMappings);

        // Get category mapping
        $catMapping = ThawaniCategoryMapping::where('store_id', $storeId)
            ->where('category_id', $product->category_id)
            ->first();

        $existingMapping = ThawaniProductMapping::where('store_id', $storeId)
            ->where('product_id', $product->id)
            ->first();

        // Build payload in the format Thawani's syncProducts expects
        $productPayload = array_merge($mapped, [
            'wameed_product_id' => $product->id,
            'name' => $product->name,
            'name_ar' => $product->name_ar ?? $product->name,
            'description' => $product->description ?? '',
            'description_ar' => $product->description_ar ?? '',
            'price' => (float) ($product->sell_price ?? $product->price ?? 0),
            'offer_price' => $product->offer_price ? (float) $product->offer_price : null,
            'quantity' => $product->quantity ?? 0,
            'barcode' => $product->barcode,
            'is_active' => (bool) ($product->is_active ?? true),
            'action' => $existingMapping ? 'update' : 'create',
        ]);
        if ($catMapping) {
            $productPayload['wameed_category_id'] = $product->category_id;
        }

        $result = $client->post('products/sync', [
            'products' => [$productPayload],
        ]);

        if ($result['success'] && isset($result['data']['results'])) {
            foreach ($result['data']['results'] as $syncResult) {
                if (($syncResult['status'] ?? '') !== 'success') continue;
                ThawaniProductMapping::updateOrCreate(
                    ['store_id' => $storeId, 'product_id' => $product->id],
                    [
                        'thawani_product_id' => $syncResult['thawani_product_id'] ?? null,
                        'is_published' => (bool) ($product->is_active ?? true),
                        'last_synced_at' => now(),
                    ]
                );
            }
        }

        $this->logSync(
            $storeId, 'product', $product->id, $item->action, 'outgoing',
            $result['success'] ? 'success' : 'failed',
            ['product_id' => $product->id, 'action' => $item->action],
            $result['data'] ?? null,
            $result['success'] ? null : ($result['message'] ?? 'Product sync failed'),
            $result['http_status'] ?? null,
        );

        return $result['success'];
    }

    private function syncSingleCategory(ThawaniApiClient $client, ThawaniSyncQueue $item, array $columnMappings, string $storeId): bool
    {
        $category = Category::find($item->entity_id);

        // Handle delete action — category may already be hard-deleted
        if ($item->action === 'delete') {
            $existingMapping = ThawaniCategoryMapping::where('store_id', $storeId)
                ->where('category_id', $item->entity_id)
                ->first();

            if (!$existingMapping) {
                return true;
            }

            $categoryPayload = [
                'wameed_category_id' => $item->entity_id,
                'name' => $category->name ?? ($item->payload['name'] ?? 'deleted'),
                'action' => 'delete',
            ];

            $result = $client->post('categories/sync', [
                'categories' => [$categoryPayload],
            ]);

            if ($result['success']) {
                $existingMapping->delete();
            }

            $this->logSync(
                $storeId, 'category', $item->entity_id, 'delete', 'outgoing',
                $result['success'] ? 'success' : 'failed',
                ['category_id' => $item->entity_id, 'action' => 'delete'],
                $result['data'] ?? null,
                $result['success'] ? null : ($result['message'] ?? 'Category delete sync failed'),
                $result['http_status'] ?? null,
            );

            return $result['success'];
        }

        if (!$category) return false;

        $mapped = $this->applyColumnMappingsOutgoing($category->toArray(), $columnMappings);

        $existingMapping = ThawaniCategoryMapping::where('store_id', $storeId)
            ->where('category_id', $category->id)
            ->first();

        // Build payload in the format Thawani's syncCategories expects
        $categoryPayload = array_merge($mapped, [
            'wameed_category_id' => $category->id,
            'name' => $category->name,
            'name_ar' => $category->name_ar ?? $category->name,
            'action' => $existingMapping ? 'update' : 'create',
        ]);

        $result = $client->post('categories/sync', [
            'categories' => [$categoryPayload],
        ]);

        if ($result['success'] && isset($result['data']['results'])) {
            foreach ($result['data']['results'] as $syncResult) {
                if (($syncResult['status'] ?? '') !== 'success') continue;
                ThawaniCategoryMapping::updateOrCreate(
                    ['store_id' => $storeId, 'category_id' => $category->id],
                    [
                        'thawani_category_id' => $syncResult['thawani_category_id'] ?? null,
                        'sync_status' => 'synced',
                        'sync_direction' => 'outgoing',
                        'last_synced_at' => now(),
                    ]
                );
            }
        }

        $this->logSync(
            $storeId, 'category', $category->id, $item->action, 'outgoing',
            $result['success'] ? 'success' : 'failed',
            ['category_id' => $category->id, 'action' => $item->action],
            $result['data'] ?? null,
            $result['success'] ? null : ($result['message'] ?? 'Category sync failed'),
            $result['http_status'] ?? null,
        );

        return $result['success'];
    }

    // ==================== Column Mapping Transforms ====================

    private function applyColumnMappingsOutgoing(array $data, array $mappings): array
    {
        if (empty($mappings)) {
            return $data;
        }

        $result = [];
        foreach ($mappings as $thawaniField => $config) {
            $wameedField = $config['wameed_field'];
            $value = $data[$wameedField] ?? null;

            if ($value === null) continue;

            switch ($config['transform_type']) {
                case 'direct':
                    $result[$thawaniField] = $value;
                    break;
                case 'json_extract':
                    $locale = $config['transform_config']['locale'] ?? 'en';
                    $result[$thawaniField] = is_array($value) ? ($value[$locale] ?? $value) : $value;
                    break;
                case 'multiply':
                    $factor = $config['transform_config']['factor'] ?? 1;
                    $result[$thawaniField] = $value * $factor;
                    break;
                case 'map_value':
                    $map = $config['transform_config']['map'] ?? [];
                    $result[$thawaniField] = $map[$value] ?? $value;
                    break;
                default:
                    $result[$thawaniField] = $value;
            }
        }

        // Also include fields not in mapping directly
        foreach ($data as $key => $value) {
            if (!isset($result[$key]) && !in_array($key, array_column($mappings, 'wameed_field'))) {
                // Skip internal fields
                if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at', 'organization_id'])) continue;
            }
        }

        return $result;
    }

    private function applyColumnMappingsIncoming(array $thawaniData, array $mappings): array
    {
        if (empty($mappings)) {
            return $thawaniData;
        }

        $result = [];
        foreach ($mappings as $thawaniField => $config) {
            $value = $thawaniData[$thawaniField] ?? null;
            if ($value === null) continue;

            $wameedField = $config['wameed_field'];

            switch ($config['transform_type']) {
                case 'direct':
                    $result[$wameedField] = $value;
                    break;
                case 'json_extract':
                    // incoming: Thawani stores translatable as JSON {"en":"...","ar":"..."}
                    // Wameed stores as separate fields name / name_ar
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            $result[$wameedField] = $decoded['en'] ?? $value;
                            $arField = $wameedField . '_ar';
                            if (isset($decoded['ar'])) {
                                $result[$arField] = $decoded['ar'];
                            }
                        } else {
                            $result[$wameedField] = $value;
                        }
                    } elseif (is_array($value)) {
                        $result[$wameedField] = $value['en'] ?? reset($value);
                        if (isset($value['ar'])) {
                            $result[$wameedField . '_ar'] = $value['ar'];
                        }
                    } else {
                        $result[$wameedField] = $value;
                    }
                    break;
                case 'multiply':
                    $factor = $config['transform_config']['factor'] ?? 1;
                    $result[$wameedField] = $factor != 0 ? $value / $factor : $value;
                    break;
                case 'map_value':
                    $map = $config['transform_config']['map'] ?? [];
                    $reverseMap = array_flip($map);
                    $result[$wameedField] = $reverseMap[$value] ?? $value;
                    break;
                default:
                    $result[$wameedField] = $value;
            }
        }

        return $result;
    }

    // ==================== Column Mappings CRUD ====================

    public function getColumnMappings(?string $entityType = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = ThawaniColumnMapping::query();
        if ($entityType) {
            $query->where('entity_type', $entityType);
        }
        return $query->orderBy('entity_type')->orderBy('thawani_field')->get();
    }

    public function seedDefaultColumnMappings(): void
    {
        $defaults = [
            // Product mappings (Thawani field => Wameed field)
            ['entity_type' => 'product', 'thawani_field' => 'name', 'wameed_field' => 'name', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'name_ar', 'wameed_field' => 'name_ar', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'price', 'wameed_field' => 'sell_price', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'image', 'wameed_field' => 'image_url', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'description', 'wameed_field' => 'description', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'description_ar', 'wameed_field' => 'description_ar', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'sku', 'wameed_field' => 'sku', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'barcode', 'wameed_field' => 'barcode', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'offer_price', 'wameed_field' => 'offer_price', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'offer_start', 'wameed_field' => 'offer_start', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'offer_end', 'wameed_field' => 'offer_end', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'is_active', 'wameed_field' => 'is_active', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'product', 'thawani_field' => 'quantity', 'wameed_field' => 'min_order_qty', 'transform_type' => 'direct', 'transform_config' => null],
            // Category mappings
            ['entity_type' => 'category', 'thawani_field' => 'name', 'wameed_field' => 'name', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'category', 'thawani_field' => 'name_ar', 'wameed_field' => 'name_ar', 'transform_type' => 'direct', 'transform_config' => null],
            ['entity_type' => 'category', 'thawani_field' => 'image', 'wameed_field' => 'image_url', 'transform_type' => 'direct', 'transform_config' => null],
        ];

        foreach ($defaults as $mapping) {
            ThawaniColumnMapping::firstOrCreate(
                [
                    'entity_type' => $mapping['entity_type'],
                    'thawani_field' => $mapping['thawani_field'],
                    'wameed_field' => $mapping['wameed_field'],
                ],
                [
                    'transform_type' => $mapping['transform_type'],
                    'transform_config' => $mapping['transform_config'],
                ]
            );
        }
    }

    // ==================== Sync Logs ====================

    public function getSyncLogs(string $storeId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = ThawaniSyncLog::where('store_id', $storeId);

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 20);
    }

    // ==================== Category Mappings CRUD ====================

    public function getCategoryMappings(string $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return ThawaniCategoryMapping::where('store_id', $storeId)
            ->with('category')
            ->orderByDesc('created_at')
            ->get();
    }

    // ==================== Product Mappings CRUD ====================

    public function getProductMappings(string $storeId): array
    {
        return ThawaniProductMapping::where('store_id', $storeId)
            ->with('product')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    // ==================== Dashboard Stats ====================

    public function getOrders(string $storeId, array $filters = []): array
    {
        $query = ThawaniOrderMapping::where('store_id', $storeId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->toArray();
    }

    public function getSettlements(string $storeId, array $filters = []): array
    {
        $query = ThawaniSettlement::where('store_id', $storeId);

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->toArray();
    }

    public function getStats(string $storeId): array
    {
        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();
        $orders = ThawaniOrderMapping::where('store_id', $storeId);
        $settlements = ThawaniSettlement::where('store_id', $storeId);

        return [
            'is_connected' => $config?->is_connected ?? false,
            'thawani_store_id' => $config?->thawani_store_id,
            'total_orders' => (clone $orders)->count(),
            'total_products_mapped' => ThawaniProductMapping::where('store_id', $storeId)->count(),
            'total_categories_mapped' => ThawaniCategoryMapping::where('store_id', $storeId)->count(),
            'total_settlements' => (clone $settlements)->count(),
            'pending_orders' => (clone $orders)->where('status', 'pending')->count(),
            'pending_sync_items' => ThawaniSyncQueue::pending()->forStore($storeId)->count(),
            'sync_logs_today' => ThawaniSyncLog::where('store_id', $storeId)
                ->whereDate('created_at', today())->count(),
            'failed_syncs_today' => ThawaniSyncLog::where('store_id', $storeId)
                ->whereDate('created_at', today())
                ->where('status', 'failed')->count(),
        ];
    }

    // ==================== Queue Stats ====================

    public function getQueueStats(string $storeId): array
    {
        return [
            'pending' => ThawaniSyncQueue::where('store_id', $storeId)->where('status', 'pending')->count(),
            'processing' => ThawaniSyncQueue::where('store_id', $storeId)->where('status', 'processing')->count(),
            'completed' => ThawaniSyncQueue::where('store_id', $storeId)->where('status', 'completed')->count(),
            'failed' => ThawaniSyncQueue::where('store_id', $storeId)->where('status', 'failed')->count(),
        ];
    }

    // ==================== Order Management ====================

    public function getOrderDetail(string $storeId, string $orderId): ?ThawaniOrderMapping
    {
        return ThawaniOrderMapping::where('store_id', $storeId)
            ->where('id', $orderId)
            ->first();
    }

    public function acceptOrder(string $storeId, string $orderId): array
    {
        $order = ThawaniOrderMapping::where('store_id', $storeId)
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        if ($order->status->value !== 'new') {
            return ['success' => false, 'message' => 'Order is not in new status'];
        }

        $order->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        // Notify Thawani about status change
        $client = new ThawaniApiClient($storeId);
        if ($client->isConfigured()) {
            $client->put("orders/{$order->thawani_order_id}/status", [
                'status' => 'accepted',
            ]);
        }

        $this->logSync(
            $storeId, 'order', $orderId, 'accept', 'outgoing', 'success',
            ['order_id' => $orderId],
            ['status' => 'accepted'],
        );

        return ['success' => true, 'order' => $order->fresh()];
    }

    public function rejectOrder(string $storeId, string $orderId, string $reason): array
    {
        $order = ThawaniOrderMapping::where('store_id', $storeId)
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        if (!in_array($order->status->value, ['new', 'accepted'])) {
            return ['success' => false, 'message' => 'Order cannot be rejected in current status'];
        }

        $order->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_at' => now(),
        ]);

        // Notify Thawani about status change
        $client = new ThawaniApiClient($storeId);
        if ($client->isConfigured()) {
            $client->put("orders/{$order->thawani_order_id}/status", [
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);
        }

        $this->logSync(
            $storeId, 'order', $orderId, 'reject', 'outgoing', 'success',
            ['order_id' => $orderId, 'reason' => $reason],
        );

        return ['success' => true, 'order' => $order->fresh()];
    }

    public function updateOrderStatus(string $storeId, string $orderId, string $status): array
    {
        $allowedTransitions = [
            'accepted' => ['preparing'],
            'preparing' => ['ready'],
            'ready' => ['dispatched', 'completed'],
            'dispatched' => ['completed'],
        ];

        $order = ThawaniOrderMapping::where('store_id', $storeId)
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        $currentStatus = $order->status->value;
        $allowed = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($status, $allowed)) {
            return ['success' => false, 'message' => "Cannot transition from {$currentStatus} to {$status}"];
        }

        $updates = ['status' => $status];
        if ($status === 'preparing') $updates['prepared_at'] = now();
        if ($status === 'completed') $updates['completed_at'] = now();

        $order->update($updates);

        // Notify Thawani about status change
        $client = new ThawaniApiClient($storeId);
        if ($client->isConfigured()) {
            $client->put("orders/{$order->thawani_order_id}/status", [
                'status' => $status,
            ]);
        }

        $this->logSync(
            $storeId, 'order', $orderId, 'status_update', 'outgoing', 'success',
            ['order_id' => $orderId, 'status' => $status],
        );

        return ['success' => true, 'order' => $order->fresh()];
    }

    // ==================== Product Management (Online Menu) ====================

    public function getProductsWithMappings(string $storeId, array $filters = []): array
    {
        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();
        if (!$config) {
            return ['data' => [], 'total' => 0];
        }

        $query = Product::where('organization_id', $config->store?->organization_id)
            ->where('is_active', true)
            ->with(['thawaniProductMappings' => function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            }]);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', "%{$filters['search']}%")
                    ->orWhere('name_ar', 'ilike', "%{$filters['search']}%");
            });
        }

        if (isset($filters['is_published'])) {
            $publishedProductIds = ThawaniProductMapping::where('store_id', $storeId)
                ->where('is_published', (bool) $filters['is_published'])
                ->pluck('product_id');
            $query->whereIn('id', $publishedProductIds);
        }

        $perPage = $filters['per_page'] ?? 20;
        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function ($product) use ($storeId) {
            $mapping = $product->thawaniProductMappings->first();
            return [
                'id' => $product->id,
                'name' => $product->name,
                'name_ar' => $product->name_ar,
                'price' => (float) ($product->sell_price ?? 0),
                'image_url' => $product->image_url,
                'category_id' => $product->category_id,
                'is_published' => $mapping ? (bool) $mapping->is_published : false,
                'online_price' => $mapping ? $mapping->online_price : null,
                'display_order' => $mapping ? ($mapping->display_order ?? 0) : 0,
                'thawani_product_id' => $mapping?->thawani_product_id,
                'last_synced_at' => $mapping?->last_synced_at?->toISOString(),
                'mapping_id' => $mapping?->id,
            ];
        });

        return [
            'data' => $items->values()->toArray(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ];
    }

    public function publishProduct(string $storeId, string $productId, bool $isPublished, ?float $onlinePrice = null, ?int $displayOrder = null): array
    {
        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();

        if (!$config?->is_connected) {
            return ['success' => false, 'message' => 'Not connected to Thawani'];
        }

        $product = Product::find($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        $mapping = ThawaniProductMapping::updateOrCreate(
            ['store_id' => $storeId, 'product_id' => $productId],
            array_filter([
                'is_published' => $isPublished,
                'online_price' => $onlinePrice,
                'display_order' => $displayOrder,
                'thawani_product_id' => ThawaniProductMapping::where('store_id', $storeId)
                    ->where('product_id', $productId)
                    ->value('thawani_product_id'),
            ], fn($v) => $v !== null)
        );

        // Notify Thawani about publish status change
        if ($mapping->thawani_product_id) {
            $client = new ThawaniApiClient($storeId);
            if ($client->isConfigured()) {
                $price = $onlinePrice ?? (float) ($product->sell_price ?? 0);
                $client->put("products/{$mapping->thawani_product_id}/publish", [
                    'is_published' => $isPublished,
                    'online_price' => $price,
                ]);
            }
        }

        $this->logSync(
            $storeId, 'product', $productId, $isPublished ? 'publish' : 'unpublish', 'outgoing', 'success',
            ['product_id' => $productId, 'is_published' => $isPublished, 'online_price' => $onlinePrice],
        );

        return ['success' => true, 'mapping' => $mapping];
    }

    public function bulkPublishProducts(string $storeId, array $productIds, bool $isPublished): array
    {
        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();
        if (!$config?->is_connected) {
            return ['success' => false, 'message' => 'Not connected to Thawani'];
        }

        $updated = 0;
        foreach ($productIds as $productId) {
            ThawaniProductMapping::updateOrCreate(
                ['store_id' => $storeId, 'product_id' => $productId],
                ['is_published' => $isPublished]
            );
            $updated++;
        }

        $this->logSync(
            $storeId, 'product', null, $isPublished ? 'bulk_publish' : 'bulk_unpublish', 'outgoing', 'success',
            ['count' => $updated],
        );

        return ['success' => true, 'updated' => $updated];
    }

    // ==================== Store Availability ====================

    public function updateStoreAvailability(string $storeId, bool $isOpen, ?string $closedReason = null): array
    {
        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();
        if (!$config) {
            return ['success' => false, 'message' => 'Store not configured'];
        }

        $config->update([
            'is_store_open' => $isOpen,
            'store_closed_reason' => $isOpen ? null : $closedReason,
        ]);

        // Notify Thawani
        $client = new ThawaniApiClient($storeId);
        if ($client->isConfigured() && $config->is_connected) {
            $client->put('store/availability', [
                'is_open' => $isOpen,
                'closed_reason' => $closedReason,
            ]);
        }

        $this->logSync(
            $storeId, 'store', $storeId, 'availability_update', 'outgoing', 'success',
            ['is_open' => $isOpen, 'reason' => $closedReason],
        );

        return ['success' => true, 'is_store_open' => $isOpen];
    }

    // ==================== Inventory Sync ====================

    public function syncInventory(string $storeId): array
    {
        $client = new ThawaniApiClient($storeId);
        $config = $this->getConfig($storeId);

        if (!$config?->is_connected) {
            return ['success' => false, 'message' => 'Not connected to Thawani'];
        }

        // Get all published products with their stock levels
        $mappings = ThawaniProductMapping::where('store_id', $storeId)
            ->where('is_published', true)
            ->whereNotNull('thawani_product_id')
            ->with(['product.stockLevels' => function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            }])
            ->get();

        $inventoryUpdates = [];
        foreach ($mappings as $mapping) {
            $stockLevel = $mapping->product?->stockLevels->first();
            $available = $stockLevel ? max(0, (float)$stockLevel->quantity - (float)$stockLevel->reserved_quantity) : 0;
            $inventoryUpdates[] = [
                'thawani_product_id' => $mapping->thawani_product_id,
                'quantity' => $available,
                'is_available' => $available > 0,
            ];
        }

        if (empty($inventoryUpdates)) {
            return ['success' => true, 'updated' => 0, 'message' => 'No published products to sync'];
        }

        $result = $client->post('inventory/sync', ['inventory' => $inventoryUpdates]);

        $this->logSync(
            $storeId, 'inventory', null, 'sync', 'outgoing',
            $result['success'] ? 'success' : 'failed',
            ['count' => count($inventoryUpdates)],
            $result['data'] ?? null,
            $result['success'] ? null : ($result['message'] ?? 'Inventory sync failed'),
            $result['http_status'] ?? null,
        );

        return array_merge($result, ['updated' => count($inventoryUpdates)]);
    }

    // ==================== Webhook Order Ingestion ====================

    public function ingestOrderFromWebhook(string $storeId, array $orderData): array
    {
        $thawaniOrderId = $orderData['thawani_order_id'] ?? $orderData['id'] ?? null;

        if (!$thawaniOrderId) {
            return ['success' => false, 'message' => 'Missing order ID in webhook data'];
        }

        // Prevent duplicate ingestion
        $existing = ThawaniOrderMapping::where('thawani_order_id', $thawaniOrderId)->first();
        if ($existing) {
            return ['success' => true, 'message' => 'Order already exists', 'order' => $existing];
        }

        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();

        $order = ThawaniOrderMapping::create([
            'store_id' => $storeId,
            'thawani_order_id' => $thawaniOrderId,
            'thawani_order_number' => $orderData['order_number'] ?? $thawaniOrderId,
            'status' => 'new',
            'delivery_type' => $orderData['delivery_type'] ?? 'delivery',
            'customer_name' => $orderData['customer_name'] ?? null,
            'customer_phone' => $orderData['customer_phone'] ?? null,
            'delivery_address' => $orderData['delivery_address'] ?? null,
            'order_items' => $orderData['items'] ?? $orderData['order_items'] ?? [],
            'delivery_fee' => $orderData['delivery_fee'] ?? null,
            'notes' => $orderData['notes'] ?? null,
            'order_total' => $orderData['order_total'] ?? $orderData['total'] ?? 0,
            'commission_amount' => $orderData['commission_amount'] ?? null,
        ]);

        // Auto-accept if configured
        if ($config?->auto_accept_orders) {
            $this->acceptOrder($storeId, $order->id);
        }

        $this->logSync(
            $storeId, 'order', $order->id, 'webhook_ingestion', 'incoming', 'success',
            $orderData,
            ['order_mapping_id' => $order->id],
        );

        return ['success' => true, 'order' => $order];
    }
}
