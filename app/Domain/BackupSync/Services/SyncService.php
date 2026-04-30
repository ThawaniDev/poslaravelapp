<?php

namespace App\Domain\BackupSync\Services;

use App\Domain\BackupSync\Enums\SyncConflictResolution;
use App\Domain\BackupSync\Enums\SyncDirection;
use App\Domain\BackupSync\Enums\SyncLogStatus;
use App\Domain\BackupSync\Models\SyncConflict;
use App\Domain\BackupSync\Models\SyncLog;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncService
{
    private const MAX_BATCH_SIZE = 100;

    /**
     * Map of syncable table names to their Eloquent model + store filter column.
     */
    private function syncableModels(string $storeId): array
    {
        $store = Store::find($storeId);
        $orgId = $store?->organization_id;

        return [
            'orders' => ['model' => Order::class, 'filter' => ['store_id' => $storeId]],
            'products' => ['model' => Product::class, 'filter' => ['organization_id' => $orgId]],
            'categories' => ['model' => Category::class, 'filter' => ['organization_id' => $orgId]],
            'product_variants' => ['model' => ProductVariant::class, 'filter' => [], 'via' => 'products', 'org_id' => $orgId],
            'customers' => ['model' => Customer::class, 'filter' => [], 'global' => true],
            'stock_levels' => ['model' => StockLevel::class, 'filter' => ['store_id' => $storeId]],
            'stock_movements' => ['model' => StockMovement::class, 'filter' => ['store_id' => $storeId]],
            'store_settings' => ['model' => StoreSettings::class, 'filter' => ['store_id' => $storeId]],
        ];
    }

    /**
     * Push local changes to the cloud with batch size enforcement and auto-conflict detection.
     */
    public function push(string $storeId, array $data): array
    {
        $startedAt = now();
        $terminalId = $data['terminal_id'];
        $changes = $data['changes'] ?? [];
        $syncToken = $data['sync_token'] ?? null;
        $checksum = $data['checksum'] ?? null;

        // Validate checksum if provided
        if ($checksum !== null) {
            $computedChecksum = hash('sha256', json_encode($changes));
            if (!hash_equals($computedChecksum, $checksum)) {
                return [
                    'error' => 'checksum_mismatch',
                    'message' => 'Payload checksum verification failed',
                ];
            }
        }

        $recordsCount = 0;
        $conflicts = [];
        $totalRecords = 0;

        // Count total records for batch size validation
        foreach ($changes as $change) {
            $totalRecords += count($change['records'] ?? []);
        }

        if ($totalRecords > self::MAX_BATCH_SIZE) {
            return [
                'error' => 'batch_too_large',
                'message' => 'Maximum ' . self::MAX_BATCH_SIZE . ' records per push. Got ' . $totalRecords,
                'max_batch_size' => self::MAX_BATCH_SIZE,
            ];
        }

        $syncableModels = $this->syncableModels($storeId);

        foreach ($changes as $change) {
            $tableName = $change['table'];
            $records = $change['records'] ?? [];

            if (!isset($syncableModels[$tableName])) {
                continue;
            }

            foreach ($records as $record) {
                $recordId = $record['id'] ?? Str::uuid()->toString();
                $existingConflict = $this->detectConflict($storeId, $tableName, $recordId, $record, $syncToken);

                if ($existingConflict) {
                    $conflicts[] = $existingConflict;
                } else {
                    // Apply the change to the database
                    $this->applyChange($tableName, $recordId, $record, $syncableModels[$tableName]);
                    $recordsCount++;
                }
            }
        }

        $completedAt = now();
        $status = empty($conflicts) ? SyncLogStatus::Success : SyncLogStatus::Partial;

        $log = SyncLog::create([
            'store_id' => $storeId,
            'terminal_id' => $terminalId,
            'direction' => SyncDirection::Push,
            'records_count' => $recordsCount,
            'duration_ms' => (int) $startedAt->diffInMilliseconds($completedAt),
            'status' => $status,
            'error_message' => empty($conflicts) ? null : count($conflicts) . ' conflict(s) detected',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        $newSyncToken = $completedAt->toIso8601String();

        return [
            'sync_log_id' => $log->id,
            'records_synced' => $recordsCount,
            'conflicts_count' => count($conflicts),
            'conflicts' => $conflicts,
            'sync_token' => $newSyncToken,
            'server_timestamp' => $completedAt->toIso8601String(),
            'checksum' => hash('sha256', json_encode($conflicts)),
        ];
    }

    /**
     * Pull cloud changes since last sync token (delta sync with real data).
     */
    public function pull(string $storeId, array $params): array
    {
        $startedAt = now();
        $terminalId = $params['terminal_id'];
        $syncToken = $params['sync_token'] ?? null;
        $requestedTables = $params['tables'] ?? [];

        $syncableModels = $this->syncableModels($storeId);

        // If no specific tables requested, pull all syncable tables
        if (empty($requestedTables)) {
            $requestedTables = array_keys($syncableModels);
        }

        // Determine the "since" timestamp from the sync token
        $since = null;
        if ($syncToken) {
            $since = Carbon::parse($syncToken);
        }

        $changes = [];
        $recordsCount = 0;

        foreach ($requestedTables as $table) {
            if (!isset($syncableModels[$table])) {
                continue;
            }

            $config = $syncableModels[$table];
            $modelClass = $config['model'];
            $query = $modelClass::query();

            // Apply store/org filter
            foreach ($config['filter'] as $column => $value) {
                $query->where($column, $value);
            }

            // Handle special cases (product_variants via products)
            if (isset($config['via']) && $config['via'] === 'products') {
                $query->whereHas('product', function ($q) use ($config) {
                    $q->where('organization_id', $config['org_id']);
                });
            }

            // Delta: only records updated since the sync token
            if ($since) {
                $query->where('updated_at', '>', $since);
            }

            // Enforce max batch size per table
            $records = $query->limit(self::MAX_BATCH_SIZE)->get();

            // Check for soft-deleted records (deleted since last sync)
            $deletedIds = [];
            if ($since && method_exists($modelClass, 'trashed')) {
                $deletedIds = $modelClass::onlyTrashed()
                    ->where('deleted_at', '>', $since);

                foreach ($config['filter'] as $column => $value) {
                    $deletedIds->where($column, $value);
                }

                $deletedIds = $deletedIds->pluck('id')->toArray();
            }

            $tableRecords = $records->map(fn ($r) => $r->toArray())->toArray();
            $recordsCount += count($tableRecords);

            $changes[] = [
                'table' => $table,
                'records' => $tableRecords,
                'deleted_ids' => $deletedIds,
                'has_more' => $records->count() >= self::MAX_BATCH_SIZE,
            ];
        }

        $completedAt = now();

        $log = SyncLog::create([
            'store_id' => $storeId,
            'terminal_id' => $terminalId,
            'direction' => SyncDirection::Pull,
            'records_count' => $recordsCount,
            'duration_ms' => (int) $startedAt->diffInMilliseconds($completedAt),
            'status' => SyncLogStatus::Success,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        $newToken = $completedAt->toIso8601String();

        return [
            'sync_log_id' => $log->id,
            'changes' => $changes,
            'records_count' => $recordsCount,
            'sync_token' => $newToken,
            'server_timestamp' => $completedAt->toIso8601String(),
            'checksum' => hash('sha256', json_encode($changes)),
        ];
    }

    /**
     * Full sync — initial download of all data for a store.
     */
    public function fullSync(string $storeId, string $terminalId): array
    {
        $startedAt = now();
        $recordsCount = 0;
        $syncableModels = $this->syncableModels($storeId);
        $store = Store::find($storeId);
        $orgId = $store?->organization_id;

        // Priority-ordered categories for full sync
        $categoryMap = [
            'catalog' => ['categories', 'products', 'product_variants'],
            'inventory' => ['stock_levels', 'stock_movements'],
            'transactions' => ['orders'],
            'customers' => ['customers'],
            'settings' => ['store_settings'],
        ];

        $data = [];
        foreach ($categoryMap as $category => $tables) {
            $categoryData = [];
            foreach ($tables as $table) {
                if (!isset($syncableModels[$table])) {
                    $categoryData[$table] = [];
                    continue;
                }

                $config = $syncableModels[$table];
                $modelClass = $config['model'];
                $query = $modelClass::query();

                foreach ($config['filter'] as $column => $value) {
                    $query->where($column, $value);
                }

                if (isset($config['via']) && $config['via'] === 'products') {
                    $query->whereHas('product', function ($q) use ($config) {
                        $q->where('organization_id', $config['org_id']);
                    });
                }

                $records = $query->get();
                $categoryData[$table] = $records->map(fn ($r) => $r->toArray())->toArray();
                $recordsCount += count($categoryData[$table]);
            }
            $data[$category] = $categoryData;
        }

        $completedAt = now();

        $log = SyncLog::create([
            'store_id' => $storeId,
            'terminal_id' => $terminalId,
            'direction' => SyncDirection::Full,
            'records_count' => $recordsCount,
            'duration_ms' => (int) $startedAt->diffInMilliseconds($completedAt),
            'status' => SyncLogStatus::Success,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        return [
            'sync_log_id' => $log->id,
            'data' => $data,
            'records_count' => $recordsCount,
            'sync_token' => $completedAt->toIso8601String(),
            'server_timestamp' => $completedAt->toIso8601String(),
            'checksum' => hash('sha256', json_encode($data)),
        ];
    }

    /**
     * Get sync status and server health.
     */
    public function status(string $storeId): array
    {
        $lastSync = SyncLog::where('store_id', $storeId)
            ->orderByDesc('started_at')
            ->first();

        $pendingConflicts = SyncConflict::where('store_id', $storeId)
            ->whereNull('resolved_at')
            ->count();

        $recentLogs = SyncLog::where('store_id', $storeId)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        $failedCount = SyncLog::where('store_id', $storeId)
            ->where('status', SyncLogStatus::Failed)
            ->where('started_at', '>=', now()->subDay())
            ->count();

        return [
            'server_online' => true,
            'server_timestamp' => now()->toIso8601String(),
            'last_sync' => $lastSync ? [
                'id' => $lastSync->id,
                'direction' => $lastSync->direction->value,
                'status' => $lastSync->status->value,
                'records_count' => $lastSync->records_count,
                'started_at' => $lastSync->started_at->toIso8601String(),
                'completed_at' => $lastSync->completed_at?->toIso8601String(),
            ] : null,
            'pending_conflicts' => $pendingConflicts,
            'failed_syncs_24h' => $failedCount,
            'recent_logs' => $recentLogs->map(fn ($log) => [
                'id' => $log->id,
                'direction' => $log->direction->value,
                'status' => $log->status->value,
                'records_count' => $log->records_count,
                'started_at' => $log->started_at->toIso8601String(),
            ])->toArray(),
        ];
    }

    /**
     * Resolve a conflict.
     */
    public function resolveConflict(string $storeId, string $conflictId, array $data): ?SyncConflict
    {
        $conflict = SyncConflict::where('id', $conflictId)
            ->where('store_id', $storeId)
            ->whereNull('resolved_at')
            ->first();

        if (!$conflict) {
            return null;
        }

        $resolution = SyncConflictResolution::from($data['resolution']);

        // Apply the resolution
        if ($resolution === SyncConflictResolution::LocalWins) {
            $this->applyConflictResolution($conflict, $conflict->local_data);
        } elseif ($resolution === SyncConflictResolution::CloudWins) {
            $this->applyConflictResolution($conflict, $conflict->cloud_data);
        } elseif ($resolution === SyncConflictResolution::Merged && isset($data['merged_data'])) {
            $this->applyConflictResolution($conflict, $data['merged_data']);
        }

        $conflict->update([
            'resolution' => $resolution,
            'resolved_by' => $data['resolved_by'] ?? auth()->id(),
            'resolved_at' => now(),
        ]);

        return $conflict->fresh();
    }

    /**
     * List conflicts for a store.
     */
    public function listConflicts(string $storeId, array $filters = []): array
    {
        $query = SyncConflict::where('store_id', $storeId);

        if (isset($filters['status'])) {
            if ($filters['status'] === 'unresolved') {
                $query->whereNull('resolved_at');
            } elseif ($filters['status'] === 'resolved') {
                $query->whereNotNull('resolved_at');
            }
        }

        if (isset($filters['table_name'])) {
            $query->where('table_name', $filters['table_name']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $paginated = $query->orderByDesc('detected_at')->paginate($perPage);

        return [
            'conflicts' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * Heartbeat — lightweight connectivity check with optional small push.
     */
    public function heartbeat(string $storeId, array $data = []): array
    {
        $terminalId = $data['terminal_id'] ?? null;
        $smallPush = $data['changes'] ?? [];
        $recordsCount = 0;

        if (!empty($smallPush) && $terminalId) {
            foreach ($smallPush as $change) {
                $recordsCount += count($change['records'] ?? []);
            }

            SyncLog::create([
                'store_id' => $storeId,
                'terminal_id' => $terminalId,
                'direction' => SyncDirection::Push,
                'records_count' => $recordsCount,
                'duration_ms' => 0,
                'status' => SyncLogStatus::Success,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }

        $pendingConflicts = SyncConflict::where('store_id', $storeId)
            ->whereNull('resolved_at')
            ->count();

        return [
            'alive' => true,
            'server_timestamp' => now()->toIso8601String(),
            'records_pushed' => $recordsCount,
            'pending_conflicts' => $pendingConflicts,
        ];
    }

    /**
     * Auto-detect conflicts by comparing timestamps between local and cloud data.
     * Uses Last-Write-Wins (LWW) for simple conflicts, flags complex ones for manual resolution.
     */
    private function detectConflict(string $storeId, string $tableName, string $recordId, array $localData, ?string $syncToken): ?array
    {
        $syncableModels = $this->syncableModels($storeId);
        if (!isset($syncableModels[$tableName])) {
            return null;
        }

        $config = $syncableModels[$tableName];
        $modelClass = $config['model'];
        $cloudRecord = $modelClass::find($recordId);

        // No cloud record means no conflict (new record)
        if (!$cloudRecord) {
            return null;
        }

        // Compare updated_at timestamps for automatic conflict detection
        $cloudUpdatedAt = $cloudRecord->updated_at;
        $localUpdatedAt = isset($localData['updated_at']) ? Carbon::parse($localData['updated_at']) : null;

        // If cloud was updated after the sync token, there's a potential conflict
        $syncTokenTime = $syncToken ? Carbon::parse($syncToken) : null;

        if ($syncTokenTime && $cloudUpdatedAt && $cloudUpdatedAt->isAfter($syncTokenTime)) {
            // Cloud was modified since last sync — conflict detected
            $conflict = SyncConflict::create([
                'store_id' => $storeId,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'local_data' => $localData,
                'cloud_data' => $cloudRecord->toArray(),
                'detected_at' => now(),
            ]);

            return [
                'id' => $conflict->id,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'local_updated_at' => $localUpdatedAt?->toIso8601String(),
                'cloud_updated_at' => $cloudUpdatedAt->toIso8601String(),
            ];
        }

        // Also detect if explicitly flagged by the client
        if (isset($localData['_conflict']) && $localData['_conflict'] === true) {
            $conflict = SyncConflict::create([
                'store_id' => $storeId,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'local_data' => $localData,
                'cloud_data' => $cloudRecord->toArray(),
                'detected_at' => now(),
            ]);

            return [
                'id' => $conflict->id,
                'table_name' => $tableName,
                'record_id' => $recordId,
            ];
        }

        return null;
    }

    /**
     * Apply a pushed change to the database.
     */
    private function applyChange(string $tableName, string $recordId, array $data, array $config): void
    {
        $modelClass = $config['model'];

        // Strip internal sync metadata fields
        $cleanData = collect($data)->except(['_conflict', '_cloud_data', '_sync_version'])->toArray();

        $existing = $modelClass::find($recordId);
        if ($existing) {
            $existing->forceFill($cleanData)->save();
        } else {
            $cleanData['id'] = $recordId;
            $modelClass::forceCreate($cleanData);
        }
    }

    /**
     * Paginated sync log history for a store.
     */
    public function listLogs(string $storeId, array $filters = []): array
    {
        $query = SyncLog::where('store_id', $storeId);

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['terminal_id'])) {
            $query->where('terminal_id', $filters['terminal_id']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 25), 100);
        $paginated = $query->orderByDesc('started_at')->paginate($perPage);

        return [
            'logs' => $paginated->map(fn ($log) => [
                'id' => $log->id,
                'terminal_id' => $log->terminal_id,
                'direction' => $log->direction->value,
                'records_count' => $log->records_count,
                'duration_ms' => $log->duration_ms,
                'status' => $log->status->value,
                'error_message' => $log->error_message,
                'started_at' => $log->started_at?->toIso8601String(),
                'completed_at' => $log->completed_at?->toIso8601String(),
            ])->toArray(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * Apply a conflict resolution by updating the record with chosen data.
     */
    private function applyConflictResolution(SyncConflict $conflict, array $resolvedData): void
    {
        $syncableModels = $this->syncableModels($conflict->store_id);
        if (!isset($syncableModels[$conflict->table_name])) {
            return;
        }

        $config = $syncableModels[$conflict->table_name];
        $modelClass = $config['model'];
        $record = $modelClass::find($conflict->record_id);

        if ($record) {
            $cleanData = collect($resolvedData)->except(['id', '_conflict', '_cloud_data', '_sync_version'])->toArray();
            $record->update($cleanData);
        }
    }
}
