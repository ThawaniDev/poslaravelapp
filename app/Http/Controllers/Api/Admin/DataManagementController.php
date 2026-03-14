<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\BackupSync\Models\BackupHistory;
use App\Domain\BackupSync\Models\DatabaseBackup;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use App\Domain\BackupSync\Models\SyncConflict;
use App\Domain\BackupSync\Models\SyncLog;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DataManagementController extends BaseApiController
{
    // ──────────────── Database Backups ────────────────

    public function listDatabaseBackups(Request $request): JsonResponse
    {
        $query = DatabaseBackup::query()->orderByDesc('started_at');

        if ($request->filled('backup_type')) {
            $query->where('backup_type', $request->backup_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('file_path', 'like', "%{$request->search}%");
        }

        return $this->success($query->paginate($request->integer('per_page', 15)), 'Database backups retrieved');
    }

    public function createDatabaseBackup(Request $request): JsonResponse
    {
        $request->validate([
            'backup_type'    => 'required|string|in:auto_daily,auto_weekly,manual',
            'file_path'      => 'required|string',
            'file_size_bytes' => 'nullable|integer|min:0',
        ]);

        $backup = DatabaseBackup::forceCreate([
            'id'              => Str::uuid()->toString(),
            'backup_type'     => $request->backup_type,
            'file_path'       => $request->file_path,
            'file_size_bytes' => $request->file_size_bytes,
            'status'          => 'in_progress',
            'started_at'      => now(),
        ]);

        return $this->created($backup, 'Database backup initiated');
    }

    public function showDatabaseBackup(string $backupId): JsonResponse
    {
        $backup = DatabaseBackup::find($backupId);
        if (!$backup) {
            return $this->notFound('Database backup not found');
        }
        return $this->success($backup, 'Database backup retrieved');
    }

    public function completeDatabaseBackup(Request $request, string $backupId): JsonResponse
    {
        $backup = DatabaseBackup::find($backupId);
        if (!$backup) {
            return $this->notFound('Database backup not found');
        }

        $request->validate([
            'status'          => 'required|string|in:completed,failed',
            'error_message'   => 'nullable|string',
            'file_size_bytes' => 'nullable|integer|min:0',
        ]);

        $backup->forceFill([
            'status'          => $request->status,
            'error_message'   => $request->error_message,
            'file_size_bytes' => $request->file_size_bytes ?? $backup->file_size_bytes,
            'completed_at'    => now(),
        ])->save();

        return $this->success($backup->fresh(), 'Database backup updated');
    }

    // ──────────────── Backup History (per provider/store) ────────────────

    public function listBackupHistory(Request $request): JsonResponse
    {
        $query = BackupHistory::query()->orderByDesc('created_at');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('backup_type')) {
            $query->where('backup_type', $request->backup_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('local_path', 'like', "%{$s}%")
                  ->orWhere('cloud_key', 'like', "%{$s}%");
            });
        }

        return $this->success($query->paginate($request->integer('per_page', 15)), 'Backup history retrieved');
    }

    public function showBackupHistoryItem(string $itemId): JsonResponse
    {
        $item = BackupHistory::find($itemId);
        if (!$item) {
            return $this->notFound('Backup history item not found');
        }
        return $this->success($item, 'Backup history item retrieved');
    }

    // ──────────────── Sync Logs ────────────────

    public function listSyncLogs(Request $request): JsonResponse
    {
        $query = SyncLog::query()->orderByDesc('started_at');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success($query->paginate($request->integer('per_page', 15)), 'Sync logs retrieved');
    }

    public function showSyncLog(string $logId): JsonResponse
    {
        $log = SyncLog::find($logId);
        if (!$log) {
            return $this->notFound('Sync log not found');
        }
        return $this->success($log, 'Sync log retrieved');
    }

    public function syncLogSummary(Request $request): JsonResponse
    {
        $query = SyncLog::query();

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        $total = $query->count();
        $successful = (clone $query)->where('status', 'success')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $avgDuration = (int) (clone $query)->avg('duration_ms');
        $totalRecords = (int) (clone $query)->sum('records_count');

        return $this->success([
            'total_syncs'     => $total,
            'successful'      => $successful,
            'failed'          => $failed,
            'avg_duration_ms' => $avgDuration,
            'total_records'   => $totalRecords,
        ], 'Sync summary retrieved');
    }

    // ──────────────── Sync Conflicts ────────────────

    public function listSyncConflicts(Request $request): JsonResponse
    {
        $query = SyncConflict::query()->orderByDesc('detected_at');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('table_name')) {
            $query->where('table_name', $request->table_name);
        }
        if ($request->has('resolved')) {
            if (filter_var($request->resolved, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('resolved_at');
            } else {
                $query->whereNull('resolved_at');
            }
        }

        return $this->success($query->paginate($request->integer('per_page', 15)), 'Sync conflicts retrieved');
    }

    public function showSyncConflict(string $conflictId): JsonResponse
    {
        $conflict = SyncConflict::find($conflictId);
        if (!$conflict) {
            return $this->notFound('Sync conflict not found');
        }
        return $this->success($conflict, 'Sync conflict retrieved');
    }

    public function resolveSyncConflict(Request $request, string $conflictId): JsonResponse
    {
        $conflict = SyncConflict::find($conflictId);
        if (!$conflict) {
            return $this->notFound('Sync conflict not found');
        }

        $request->validate([
            'resolution' => 'required|string|in:local_wins,cloud_wins,merged',
        ]);

        $conflict->forceFill([
            'resolution'  => $request->resolution,
            'resolved_by' => $request->user('admin-api')?->id,
            'resolved_at' => now(),
        ])->save();

        return $this->success($conflict->fresh(), 'Sync conflict resolved');
    }

    // ──────────────── Provider Backup Status ────────────────

    public function listProviderBackupStatuses(Request $request): JsonResponse
    {
        $query = ProviderBackupStatus::query()->orderByDesc('updated_at');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success($query->paginate($request->integer('per_page', 15)), 'Provider backup statuses retrieved');
    }

    public function showProviderBackupStatus(string $statusId): JsonResponse
    {
        $status = ProviderBackupStatus::find($statusId);
        if (!$status) {
            return $this->notFound('Provider backup status not found');
        }
        return $this->success($status, 'Provider backup status retrieved');
    }

    public function backupOverview(): JsonResponse
    {
        $dbBackups = DatabaseBackup::query();
        $totalBackups = $dbBackups->count();
        $completedBackups = (clone $dbBackups)->where('status', 'completed')->count();
        $failedBackups = (clone $dbBackups)->where('status', 'failed')->count();
        $totalSizeBytes = (int) (clone $dbBackups)->where('status', 'completed')->sum('file_size_bytes');

        $syncLogs = SyncLog::query();
        $totalSyncs = $syncLogs->count();
        $successfulSyncs = (clone $syncLogs)->where('status', 'success')->count();

        $unresolvedConflicts = SyncConflict::whereNull('resolved_at')->count();

        return $this->success([
            'database_backups' => [
                'total'     => $totalBackups,
                'completed' => $completedBackups,
                'failed'    => $failedBackups,
                'total_size_bytes' => $totalSizeBytes,
            ],
            'sync' => [
                'total_syncs'     => $totalSyncs,
                'successful'      => $successfulSyncs,
                'unresolved_conflicts' => $unresolvedConflicts,
            ],
        ], 'Backup overview retrieved');
    }
}
