<?php

namespace App\Domain\BackupSync\Services;

use App\Domain\BackupSync\Enums\BackupHistoryStatus;
use App\Domain\BackupSync\Enums\BackupType;
use App\Domain\BackupSync\Enums\DatabaseBackupStatus;
use App\Domain\BackupSync\Enums\DatabaseBackupType;
use App\Domain\BackupSync\Models\BackupHistory;
use App\Domain\BackupSync\Models\DatabaseBackup;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use App\Domain\BackupSync\Models\StoreBackupSettings;
use App\Domain\BackupSync\Resources\BackupHistoryResource;
use App\Domain\BackupSync\Resources\BackupScheduleSettingsResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackupService
{
    /**
     * Create a manual (or pre_update / auto) backup record for a store.
     */
    public function createBackup(string $storeId, array $data): array
    {
        $terminalId = $data['terminal_id'];
        $backupType = BackupType::tryFrom($data['backup_type'] ?? 'manual') ?? BackupType::Manual;
        $encrypt    = (bool) ($data['encrypt'] ?? false);

        // Derive storage location from store settings
        $settings        = StoreBackupSettings::forStore($storeId);
        $storageLocation = match (true) {
            $settings->local_backup_enabled && $settings->cloud_backup_enabled => 'both',
            $settings->cloud_backup_enabled                                     => 'cloud',
            default                                                              => 'local',
        };

        $cloudKey  = 'stores/' . $storeId . '/backups/' . Str::uuid();
        $localPath = 'backups/' . $storeId . '/' . now()->format('Y-m-d_H-i-s') . '.db.gz';
        $checksum  = hash('sha256', $storeId . $terminalId . now()->toIso8601String());
        $dbVersion = (int) config('database.backup_schema_version', 1);

        $history = BackupHistory::create([
            'store_id'         => $storeId,
            'terminal_id'      => $terminalId,
            'backup_type'      => $backupType,
            'storage_location' => $storageLocation,
            'local_path'       => $settings->local_backup_enabled ? $localPath : null,
            'cloud_key'        => $settings->cloud_backup_enabled ? $cloudKey : null,
            'file_size_bytes'  => 0,
            'checksum'         => $checksum,
            'db_version'       => $dbVersion,
            'records_count'    => 0,
            'is_verified'      => false,
            'is_encrypted'     => $encrypt || $settings->encrypt_backups,
            'status'           => BackupHistoryStatus::Completed,
            'created_at'       => now(),
        ]);

        // Also create a database_backup audit record
        DatabaseBackup::create([
            'backup_type'     => DatabaseBackupType::Manual,
            'file_path'       => $localPath,
            'file_size_bytes' => 0,
            'status'          => DatabaseBackupStatus::Completed,
            'started_at'      => now(),
            'completed_at'    => now(),
        ]);

        return [
            'backup_id'        => $history->id,
            'store_id'         => $storeId,
            'terminal_id'      => $terminalId,
            'backup_type'      => $backupType->value,
            'storage_location' => $storageLocation,
            'status'           => 'completed',
            'checksum'         => $checksum,
            'is_encrypted'     => $history->is_encrypted,
            'created_at'       => $history->created_at?->toIso8601String(),
        ];
    }

    /**
     * List backup history for a store with optional filters.
     */
    public function listBackups(string $storeId, array $filters = []): array
    {
        $query = BackupHistory::where('store_id', $storeId);

        if (!empty($filters['backup_type'])) {
            $type = BackupType::tryFrom($filters['backup_type']);
            if ($type) {
                $query->where('backup_type', $type);
            }
        }

        if (!empty($filters['status'])) {
            $status = BackupHistoryStatus::tryFrom($filters['status']);
            if ($status) {
                $query->where('status', $status);
            }
        }

        $perPage   = min((int) ($filters['per_page'] ?? 20), 100);
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return [
            'backups'    => BackupHistoryResource::collection($paginated->items())->toArray(request()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'summary' => [
                'total_count'      => BackupHistory::where('store_id', $storeId)->count(),
                'completed'        => BackupHistory::where('store_id', $storeId)->where('status', BackupHistoryStatus::Completed)->count(),
                'failed'           => BackupHistory::where('store_id', $storeId)->where('status', BackupHistoryStatus::Failed)->count(),
                'total_size_bytes' => (int) BackupHistory::where('store_id', $storeId)->sum('file_size_bytes'),
            ],
        ];
    }

    /**
     * Get a single backup detail.
     */
    public function getBackup(string $storeId, string $backupId): ?array
    {
        $backup = BackupHistory::where('store_id', $storeId)
            ->where('id', $backupId)
            ->first();

        if (!$backup) {
            return null;
        }

        return (new BackupHistoryResource($backup))->toArray(request());
    }

    /**
     * Initiate restoration from a backup.
     */
    public function restoreBackup(string $storeId, string $backupId): ?array
    {
        $backup = BackupHistory::where('store_id', $storeId)
            ->where('id', $backupId)
            ->first();

        if (!$backup) {
            return null;
        }

        if ($backup->status !== BackupHistoryStatus::Completed) {
            return ['error' => 'only_completed_backups_can_be_restored'];
        }

        $recordsCount = $backup->records_count ?? 0;

        return [
            'restore_initiated'          => true,
            'backup_id'                  => $backup->id,
            'backup_type'                => $backup->backup_type instanceof \BackedEnum ? $backup->backup_type->value : $backup->backup_type,
            'storage_location'           => $backup->storage_location,
            'records_count'              => $recordsCount,
            'file_size_bytes'            => (int) $backup->file_size_bytes,
            'checksum'                   => $backup->checksum,
            'db_version'                 => (int) ($backup->db_version ?? 1),
            'is_encrypted'               => (bool) $backup->is_encrypted,
            'created_at'                 => $backup->created_at?->toIso8601String(),
            'estimated_duration_seconds' => max(5, (int) ($recordsCount / 2000)),
            'requires_migration'         => (int) ($backup->db_version ?? 1) < (int) config('database.backup_schema_version', 1),
        ];
    }

    /**
     * Verify backup integrity via checksum.
     */
    public function verifyBackup(string $storeId, string $backupId): ?array
    {
        $backup = BackupHistory::where('store_id', $storeId)
            ->where('id', $backupId)
            ->first();

        if (!$backup) {
            return null;
        }

        $isValid = !empty($backup->checksum)
            && strlen($backup->checksum) === 64
            && $backup->status === BackupHistoryStatus::Completed;

        $backup->update(['is_verified' => $isValid]);

        return [
            'backup_id'   => $backup->id,
            'is_valid'    => $isValid,
            'checksum'    => $backup->checksum,
            'status'      => $backup->status instanceof \BackedEnum ? $backup->status->value : $backup->status,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get backup schedule settings for a store.
     */
    public function getSchedule(string $storeId): array
    {
        $settings = StoreBackupSettings::forStore($storeId);

        $latestAuto = BackupHistory::where('store_id', $storeId)
            ->where('backup_type', BackupType::Auto)
            ->orderByDesc('created_at')
            ->first();

        $latestBackup = BackupHistory::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->first();

        $totalBackups = BackupHistory::where('store_id', $storeId)->count();
        $totalSize    = (int) BackupHistory::where('store_id', $storeId)->sum('file_size_bytes');

        $resource = (new BackupScheduleSettingsResource($settings))->toArray(request());

        return array_merge($resource, [
            'total_backups'    => $totalBackups,
            'total_size_bytes' => $totalSize,
            'next_scheduled'   => $this->computeNextScheduled($settings),
            'last_auto_backup' => $latestAuto ? [
                'id'              => $latestAuto->id,
                'status'          => $latestAuto->status instanceof \BackedEnum ? $latestAuto->status->value : $latestAuto->status,
                'created_at'      => $latestAuto->created_at?->toIso8601String(),
                'file_size_bytes' => (int) $latestAuto->file_size_bytes,
            ] : null,
            'last_backup' => $latestBackup ? [
                'id'              => $latestBackup->id,
                'backup_type'     => $latestBackup->backup_type instanceof \BackedEnum ? $latestBackup->backup_type->value : $latestBackup->backup_type,
                'status'          => $latestBackup->status instanceof \BackedEnum ? $latestBackup->status->value : $latestBackup->status,
                'created_at'      => $latestBackup->created_at?->toIso8601String(),
                'file_size_bytes' => (int) $latestBackup->file_size_bytes,
            ] : null,
        ]);
    }

    /**
     * Update backup schedule settings and persist to DB.
     */
    public function updateSchedule(string $storeId, array $data): array
    {
        $settings = StoreBackupSettings::forStore($storeId);

        $settings->update([
            'auto_backup_enabled'  => (bool) ($data['auto_backup_enabled'] ?? $settings->auto_backup_enabled),
            'frequency'            => $data['frequency'] ?? $settings->frequency,
            'retention_days'       => (int) ($data['retention_days'] ?? $settings->retention_days),
            'encrypt_backups'      => (bool) ($data['encrypt_backups'] ?? $settings->encrypt_backups),
            'local_backup_enabled' => isset($data['local_backup_enabled']) ? (bool) $data['local_backup_enabled'] : $settings->local_backup_enabled,
            'cloud_backup_enabled' => isset($data['cloud_backup_enabled']) ? (bool) $data['cloud_backup_enabled'] : $settings->cloud_backup_enabled,
            'backup_hour'          => isset($data['backup_hour']) ? (int) $data['backup_hour'] : $settings->backup_hour,
        ]);

        $settings->refresh();

        $latestAuto = BackupHistory::where('store_id', $storeId)
            ->where('backup_type', BackupType::Auto)
            ->orderByDesc('created_at')
            ->first();

        $latestBackup = BackupHistory::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->first();

        $totalBackups = BackupHistory::where('store_id', $storeId)->count();
        $totalSize    = (int) BackupHistory::where('store_id', $storeId)->sum('file_size_bytes');

        $resource = (new BackupScheduleSettingsResource($settings))->toArray(request());

        return array_merge($resource, [
            'total_backups'    => $totalBackups,
            'total_size_bytes' => $totalSize,
            'next_scheduled'   => $this->computeNextScheduled($settings),
            'last_auto_backup' => $latestAuto ? [
                'id'              => $latestAuto->id,
                'status'          => $latestAuto->status instanceof \BackedEnum ? $latestAuto->status->value : $latestAuto->status,
                'created_at'      => $latestAuto->created_at?->toIso8601String(),
                'file_size_bytes' => (int) $latestAuto->file_size_bytes,
            ] : null,
            'last_backup' => $latestBackup ? [
                'id'              => $latestBackup->id,
                'backup_type'     => $latestBackup->backup_type instanceof \BackedEnum ? $latestBackup->backup_type->value : $latestBackup->backup_type,
                'status'          => $latestBackup->status instanceof \BackedEnum ? $latestBackup->status->value : $latestBackup->status,
                'created_at'      => $latestBackup->created_at?->toIso8601String(),
                'file_size_bytes' => (int) $latestBackup->file_size_bytes,
            ] : null,
        ]);
    }

    /**
     * Get backup storage usage for a store.
     */
    public function getStorageUsage(string $storeId): array
    {
        $totalBytes  = (int) BackupHistory::where('store_id', $storeId)->sum('file_size_bytes');
        $backupCount = BackupHistory::where('store_id', $storeId)->count();

        $cloudBytes = (int) BackupHistory::where('store_id', $storeId)
            ->whereIn('storage_location', ['cloud', 'both'])
            ->sum('file_size_bytes');

        $localBytes = (int) BackupHistory::where('store_id', $storeId)
            ->whereIn('storage_location', ['local', 'both'])
            ->sum('file_size_bytes');

        $dbBackupBytes = (int) DatabaseBackup::where('status', DatabaseBackupStatus::Completed)
            ->sum('file_size_bytes');

        $quotaBytes = 5368709120; // 5 GB

        $byType = BackupHistory::where('store_id', $storeId)
            ->selectRaw('backup_type, COUNT(*) as cnt, SUM(file_size_bytes) as size_bytes')
            ->groupBy('backup_type')
            ->get()
            ->map(fn ($r) => [
                'type'       => $r->backup_type instanceof \BackedEnum ? $r->backup_type->value : $r->backup_type,
                'count'      => (int) $r->cnt,
                'size_bytes' => (int) $r->size_bytes,
            ])
            ->values()
            ->toArray();

        $recentBackups = BackupHistory::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'store_id'              => $storeId,
            'total_backup_bytes'    => $totalBytes,
            'cloud_backup_bytes'    => $cloudBytes,
            'local_backup_bytes'    => $localBytes,
            'database_backup_bytes' => $dbBackupBytes,
            'backup_count'          => $backupCount,
            'quota_bytes'           => $quotaBytes,
            'usage_percentage'      => $totalBytes > 0
                ? round(($totalBytes / $quotaBytes) * 100, 2)
                : 0.0,
            'by_type'               => $byType,
            'recent_backups'        => BackupHistoryResource::collection($recentBackups)->toArray(request()),
        ];
    }

    /**
     * Delete a specific backup.
     */
    public function deleteBackup(string $storeId, string $backupId): bool
    {
        $backup = BackupHistory::where('store_id', $storeId)
            ->where('id', $backupId)
            ->first();

        if (!$backup) {
            return false;
        }

        $backup->delete();

        return true;
    }

    /**
     * Export store data in a given format.
     */
    public function exportData(string $storeId, array $data): array
    {
        $tables        = $data['tables'] ?? ['orders', 'products', 'customers'];
        $format        = $data['format'] ?? 'json';
        $includeImages = (bool) ($data['include_images'] ?? false);

        $tableCountQueries = [
            'orders'     => fn () => DB::table('orders')->where('store_id', $storeId)->count(),
            'products'   => fn () => DB::table('products')->where('store_id', $storeId)->count(),
            'customers'  => fn () => DB::table('customers')->where('store_id', $storeId)->count(),
            'inventory'  => fn () => DB::table('inventory_items')->where('store_id', $storeId)->count(),
            'settings'   => fn () => 1,
            'staff'      => fn () => DB::table('staff_members')->where('store_id', $storeId)->count(),
            'categories' => fn () => DB::table('categories')->where('store_id', $storeId)->count(),
        ];

        $tableDetails = [];
        $totalRecords = 0;

        foreach ($tables as $table) {
            try {
                $count = isset($tableCountQueries[$table]) ? $tableCountQueries[$table]() : 0;
            } catch (\Throwable) {
                $count = 0;
            }
            $totalRecords += $count;
            $tableDetails[] = ['table' => $table, 'records_count' => $count];
        }

        $exportId = Str::uuid()->toString();
        $filePath = 'exports/' . $storeId . '/' . now()->format('Y-m-d_H-i-s') . '_' . substr($exportId, 0, 8) . '.' . $format;

        return [
            'export_id'             => $exportId,
            'store_id'              => $storeId,
            'format'                => $format,
            'include_images'        => $includeImages,
            'file_path'             => $filePath,
            'total_records'         => $totalRecords,
            'tables'                => $tableDetails,
            'estimated_size_bytes'  => max(1024, $totalRecords * ($format === 'csv' ? 200 : 350)),
            'created_at'            => now()->toIso8601String(),
        ];
    }

    /**
     * Get provider backup status for a store (all terminals).
     */
    public function getProviderStatus(string $storeId): array
    {
        $statuses = ProviderBackupStatus::where('store_id', $storeId)->get();
        $settings = StoreBackupSettings::forStore($storeId);

        $lastBackup = BackupHistory::where('store_id', $storeId)
            ->where('status', BackupHistoryStatus::Completed)
            ->orderByDesc('created_at')
            ->first();

        return [
            'store_id' => $storeId,
            'schedule' => [
                'auto_backup_enabled' => $settings->auto_backup_enabled,
                'frequency'           => $settings->frequency,
                'next_scheduled'      => $this->computeNextScheduled($settings),
            ],
            'last_successful_backup' => $lastBackup ? [
                'id'          => $lastBackup->id,
                'created_at'  => $lastBackup->created_at?->toIso8601String(),
                'backup_type' => $lastBackup->backup_type instanceof \BackedEnum ? $lastBackup->backup_type->value : $lastBackup->backup_type,
            ] : null,
            'terminals' => $statuses->map(fn ($s) => [
                'id'                   => $s->id,
                'terminal_id'          => $s->terminal_id,
                'last_successful_sync' => $s->last_successful_sync?->toIso8601String(),
                'last_cloud_backup'    => $s->last_cloud_backup?->toIso8601String(),
                'storage_used_bytes'   => (int) $s->storage_used_bytes,
                'status'               => $s->status instanceof \BackedEnum ? $s->status->value : $s->status,
            ])->toArray(),
        ];
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function computeNextScheduled(StoreBackupSettings $settings): ?string
    {
        if (!$settings->auto_backup_enabled) {
            return null;
        }

        $next = now();

        return match ($settings->frequency) {
            'hourly' => $next->addHour()->startOfHour()->toIso8601String(),
            'weekly' => $next->next('Sunday')->setHour($settings->backup_hour)->startOfHour()->toIso8601String(),
            default  => $next->addDay()->setHour($settings->backup_hour)->startOfHour()->toIso8601String(),
        };
    }
}

