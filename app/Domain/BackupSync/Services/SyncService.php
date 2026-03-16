<?php

namespace App\Domain\BackupSync\Services;

use App\Domain\BackupSync\Enums\SyncConflictResolution;
use App\Domain\BackupSync\Enums\SyncDirection;
use App\Domain\BackupSync\Enums\SyncLogStatus;
use App\Domain\BackupSync\Models\SyncConflict;
use App\Domain\BackupSync\Models\SyncLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SyncService
{
    /**
     * Push local changes to the cloud.
     */
    public function push(string $storeId, array $data): array
    {
        $startedAt = now();
        $terminalId = $data['terminal_id'];
        $changes = $data['changes'] ?? [];
        $syncToken = $data['sync_token'] ?? null;

        $recordsCount = 0;
        $conflicts = [];

        foreach ($changes as $change) {
            $tableName = $change['table'];
            $records = $change['records'] ?? [];

            foreach ($records as $record) {
                $recordId = $record['id'] ?? Str::uuid()->toString();
                $existingConflict = $this->detectConflict($storeId, $tableName, $recordId, $record, $syncToken);

                if ($existingConflict) {
                    $conflicts[] = $existingConflict;
                } else {
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
            'duration_ms' => $startedAt->diffInMilliseconds($completedAt),
            'status' => $status,
            'error_message' => empty($conflicts) ? null : count($conflicts) . ' conflict(s) detected',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        $newSyncToken = Str::uuid()->toString();

        return [
            'sync_log_id' => $log->id,
            'records_synced' => $recordsCount,
            'conflicts_count' => count($conflicts),
            'conflicts' => $conflicts,
            'sync_token' => $newSyncToken,
            'server_timestamp' => $completedAt->toIso8601String(),
        ];
    }

    /**
     * Pull cloud changes since last sync token.
     */
    public function pull(string $storeId, array $params): array
    {
        $startedAt = now();
        $terminalId = $params['terminal_id'];
        $syncToken = $params['sync_token'] ?? null;
        $tables = $params['tables'] ?? [];

        // Simulate delta pull: return changes since last sync
        $changes = [];
        $recordsCount = 0;

        // In production, query each table for records updated after the sync token timestamp.
        // For now, return empty delta with a new token.
        foreach ($tables as $table) {
            $changes[] = [
                'table' => $table,
                'records' => [],
                'deleted_ids' => [],
            ];
        }

        $completedAt = now();

        $log = SyncLog::create([
            'store_id' => $storeId,
            'terminal_id' => $terminalId,
            'direction' => SyncDirection::Pull,
            'records_count' => $recordsCount,
            'duration_ms' => $startedAt->diffInMilliseconds($completedAt),
            'status' => SyncLogStatus::Success,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        return [
            'sync_log_id' => $log->id,
            'changes' => $changes,
            'records_count' => $recordsCount,
            'sync_token' => Str::uuid()->toString(),
            'server_timestamp' => $completedAt->toIso8601String(),
        ];
    }

    /**
     * Full sync — initial download of all data.
     */
    public function fullSync(string $storeId, string $terminalId): array
    {
        $startedAt = now();
        $recordsCount = 0;

        // Categories of data to sync with priority order
        $categories = [
            'transactions' => ['orders', 'payments', 'refunds'],
            'inventory' => ['products', 'stock_movements'],
            'catalog' => ['categories', 'product_variants'],
            'customers' => ['customers'],
            'settings' => ['store_settings', 'tax_rates'],
        ];

        $data = [];
        foreach ($categories as $category => $tables) {
            $categoryData = [];
            foreach ($tables as $table) {
                $categoryData[$table] = [];
            }
            $data[$category] = $categoryData;
        }

        $completedAt = now();

        $log = SyncLog::create([
            'store_id' => $storeId,
            'terminal_id' => $terminalId,
            'direction' => SyncDirection::Full,
            'records_count' => $recordsCount,
            'duration_ms' => $startedAt->diffInMilliseconds($completedAt),
            'status' => SyncLogStatus::Success,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        return [
            'sync_log_id' => $log->id,
            'data' => $data,
            'records_count' => $recordsCount,
            'sync_token' => Str::uuid()->toString(),
            'server_timestamp' => $completedAt->toIso8601String(),
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

        $conflict->update([
            'resolution' => SyncConflictResolution::from($data['resolution']),
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
     * Detect potential conflict between local and cloud data.
     */
    private function detectConflict(string $storeId, string $tableName, string $recordId, array $localData, ?string $syncToken): ?array
    {
        // Simple conflict detection: check if a newer version exists on the server
        // In production, compare timestamps and data hashes
        // For MVP, conflicts are detected only when explicitly flagged by the client
        if (isset($localData['_conflict']) && $localData['_conflict'] === true) {
            $conflict = SyncConflict::create([
                'store_id' => $storeId,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'local_data' => $localData,
                'cloud_data' => $localData['_cloud_data'] ?? [],
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
}
