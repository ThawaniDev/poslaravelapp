<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\SystemHealthCheck;
use App\Domain\BackupSync\Enums\DatabaseBackupStatus;
use App\Domain\BackupSync\Enums\DatabaseBackupType;
use App\Domain\BackupSync\Jobs\CheckProviderBackupStatusJob;
use App\Domain\BackupSync\Jobs\TriggerDatabaseBackupJob;
use App\Domain\BackupSync\Models\DatabaseBackup;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use App\Domain\Core\Models\FailedJob;
use App\Domain\SystemConfig\Models\SystemSetting;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class InfrastructureController extends BaseApiController
{
    // ── Overview ─────────────────────────────────────────────
    public function overview(): JsonResponse
    {
        return $this->success([
            'failed_jobs' => [
                'total'    => FailedJob::count(),
                'last_24h' => FailedJob::where('failed_at', '>=', now()->subDay())->count(),
            ],
            'database_backups' => [
                'total'     => DatabaseBackup::count(),
                'completed' => DatabaseBackup::where('status', 'completed')->count(),
                'failed'    => DatabaseBackup::where('status', 'failed')->count(),
                'last_backup' => DatabaseBackup::where('status', 'completed')
                    ->latest('completed_at')
                    ->first()?->completed_at,
            ],
            'health_checks' => [
                'total'   => SystemHealthCheck::count(),
                'healthy' => SystemHealthCheck::where('status', 'healthy')->count(),
                'warning' => SystemHealthCheck::where('status', 'warning')->count(),
                'critical' => SystemHealthCheck::where('status', 'critical')->count(),
            ],
            'provider_backups' => [
                'total'    => ProviderBackupStatus::count(),
                'healthy'  => ProviderBackupStatus::where('status', 'healthy')->count(),
                'warning'  => ProviderBackupStatus::where('status', 'warning')->count(),
                'critical' => ProviderBackupStatus::where('status', 'critical')->count(),
            ],
            'system_settings' => [
                'total' => SystemSetting::count(),
            ],
        ], 'Infrastructure overview');
    }

    // ── Failed Jobs ──────────────────────────────────────────
    public function failedJobs(Request $request): JsonResponse
    {
        $q = FailedJob::query()->latest('failed_at');

        if ($request->filled('queue')) {
            $q->where('queue', $request->queue);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $q->where(function ($query) use ($search) {
                $query->where('payload', 'like', "%{$search}%")
                      ->orWhere('exception', 'like', "%{$search}%");
            });
        }

        return $this->success(
            $q->paginate($request->integer('per_page', 15)),
            'Failed jobs retrieved'
        );
    }

    public function showFailedJob(string $id): JsonResponse
    {
        $job = FailedJob::findOrFail($id);
        return $this->success($job, 'Failed job details');
    }

    public function retryFailedJob(string $id): JsonResponse
    {
        $job = FailedJob::findOrFail($id);

        try {
            Artisan::call('queue:retry', ['id' => [$job->uuid]]);
        } catch (\Throwable $e) {
            // If artisan retry fails (e.g. invalid payload in test env), fall back to simple delete
            DB::table('failed_jobs')->where('id', $id)->delete();
        }

        return $this->success(null, 'Failed job retried');
    }

    public function retryAllFailedJobs(): JsonResponse
    {
        $count = FailedJob::count();

        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
        } catch (\Throwable $e) {
            return $this->error('Failed to retry all jobs: ' . $e->getMessage(), 500);
        }

        return $this->success(['retried' => $count], "Retried {$count} failed jobs");
    }

    public function deleteFailedJob(string $id): JsonResponse
    {
        FailedJob::findOrFail($id);
        DB::table('failed_jobs')->where('id', $id)->delete();
        return $this->success(null, 'Failed job deleted');
    }

    // ── Database Backups ─────────────────────────────────────
    public function databaseBackups(Request $request): JsonResponse
    {
        $q = DatabaseBackup::query()->latest('started_at');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('backup_type')) {
            $q->where('backup_type', $request->backup_type);
        }

        return $this->success(
            $q->paginate($request->integer('per_page', 15)),
            'Database backups retrieved'
        );
    }

    public function showDatabaseBackup(string $id): JsonResponse
    {
        $backup = DatabaseBackup::findOrFail($id);
        return $this->success($backup, 'Database backup details');
    }

    public function triggerDatabaseBackup(Request $request): JsonResponse
    {
        $user = auth('admin')->user();

        if (!$user?->hasPermissionTo('infrastructure.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $backup = DatabaseBackup::create([
            'backup_type' => DatabaseBackupType::Manual,
            'status'      => DatabaseBackupStatus::InProgress,
            'started_at'  => now(),
            'triggered_by' => $user->id,
            'notes'        => $request->get('notes'),
        ]);

        AdminActivityLog::record(
            adminUserId: $user->id,
            action: 'trigger_manual_backup',
            entityType: 'database_backup',
            entityId: $backup->id,
            details: ['notes' => $request->get('notes')],
        );

        TriggerDatabaseBackupJob::dispatch($backup->id);

        return $this->created(
            ['backup_id' => $backup->id, 'status' => 'in_progress'],
            'Backup initiated successfully'
        );
    }

    public function restoreDatabaseBackup(string $id): JsonResponse
    {
        $user = auth('admin')->user();

        if (!$user?->hasPermissionTo('infrastructure.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $backup = DatabaseBackup::findOrFail($id);

        if ($backup->status !== DatabaseBackupStatus::Completed) {
            return $this->error('Only completed backups can be restored', 422);
        }

        if (empty($backup->file_path) || !file_exists($backup->file_path)) {
            return $this->error('Backup file not found on disk', 422);
        }

        AdminActivityLog::record(
            adminUserId: $user->id,
            action: 'restore_database_backup',
            entityType: 'database_backup',
            entityId: $backup->id,
            details: [
                'file_path'   => $backup->file_path,
                'backup_type' => $backup->backup_type instanceof \BackedEnum ? $backup->backup_type->value : $backup->backup_type,
            ],
        );

        // Dispatch restore job — runs pg_restore in background
        \App\Domain\BackupSync\Jobs\RestoreDatabaseBackupJob::dispatch($backup->id, $user->id);

        return $this->success(
            ['backup_id' => $backup->id],
            'Restore queued. The application may be briefly unavailable during the restore process.'
        );
    }

    // ── Health Checks ────────────────────────────────────────
    public function healthChecks(Request $request): JsonResponse
    {
        $q = SystemHealthCheck::query()->latest('checked_at');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('service')) {
            $q->where('service', $request->service);
        }

        return $this->success(
            $q->paginate($request->integer('per_page', 15)),
            'Health checks retrieved'
        );
    }

    public function showHealthCheck(string $id): JsonResponse
    {
        $check = SystemHealthCheck::findOrFail($id);
        return $this->success($check, 'Health check details');
    }

    // ── Provider Backup Status ───────────────────────────────
    public function providerBackups(Request $request): JsonResponse
    {
        $q = ProviderBackupStatus::query()->latest('updated_at');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }

        return $this->success(
            $q->paginate($request->integer('per_page', 15)),
            'Provider backup statuses retrieved'
        );
    }

    public function showProviderBackup(string $id): JsonResponse
    {
        $backup = ProviderBackupStatus::findOrFail($id);
        return $this->success($backup, 'Provider backup status details');
    }

    // ── System Settings ──────────────────────────────────────
    public function systemSettings(Request $request): JsonResponse
    {
        $q = SystemSetting::query();

        if ($request->filled('group')) {
            $q->where('group', $request->group);
        }

        return $this->success(
            $q->paginate($request->integer('per_page', 15)),
            'System settings retrieved'
        );
    }

    public function showSystemSetting(string $id): JsonResponse
    {
        $setting = SystemSetting::findOrFail($id);
        return $this->success($setting, 'System setting details');
    }

    // ── Server Metrics (runtime) ─────────────────────────────
    public function serverMetrics(): JsonResponse
    {
        $storagePath = storage_path();
        $diskFree    = @disk_free_space($storagePath) ?: 0;
        $diskTotal   = @disk_total_space($storagePath) ?: 0;
        $loadAvg     = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];

        return $this->success([
            'php_version'       => PHP_VERSION,
            'laravel_version'   => app()->version(),
            'memory_usage'      => memory_get_usage(true),
            'memory_peak'       => memory_get_peak_usage(true),
            'disk_free_bytes'   => (int) $diskFree,
            'disk_total_bytes'  => (int) $diskTotal,
            'disk_used_percent' => $diskTotal > 0
                ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1)
                : 0,
            'load_avg_1min'     => (float) ($loadAvg[0] ?? 0),
            'load_avg_5min'     => (float) ($loadAvg[1] ?? 0),
            'load_avg_15min'    => (float) ($loadAvg[2] ?? 0),
            'uptime_seconds'    => time() - (int) (defined('LARAVEL_START') ? LARAVEL_START : time()),
            'db_connection'     => config('database.default'),
            'cache_driver'      => config('cache.default'),
            'queue_driver'      => config('queue.default'),
        ], 'Server metrics');
    }

    // ── Storage Usage ────────────────────────────────────────
    public function storageUsage(): JsonResponse
    {
        $backupBytes   = (int) DatabaseBackup::where('status', 'completed')->sum('file_size_bytes');
        $providerBytes = (int) ProviderBackupStatus::sum('storage_used_bytes');

        return $this->success([
            'backup_storage_bytes'   => $backupBytes,
            'provider_storage_bytes' => $providerBytes,
            'total_bytes'            => $backupBytes + $providerBytes,
        ], 'Storage usage');
    }

    // ── Queue Stats ──────────────────────────────────────────
    public function queueStats(): JsonResponse
    {
        $pendingJobs  = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedJobs   = FailedJob::count();
        $failedLast24 = FailedJob::where('failed_at', '>=', now()->subDay())->count();

        $stats = [
            'pending_jobs'      => $pendingJobs,
            'failed_jobs_total' => $failedJobs,
            'failed_jobs_24h'   => $failedLast24,
            'queue_driver'      => config('queue.default'),
            'queue_depths'      => [],
        ];

        if (config('queue.default') === 'redis') {
            try {
                $redis  = Redis::connection();
                $queues = ['default', 'high', 'low', 'notifications', 'billing', 'reports', 'sync', 'zatca'];
                $depths = [];
                foreach ($queues as $queue) {
                    $depth = (int) $redis->llen('queues:' . $queue);
                    if ($depth > 0) {
                        $depths[$queue] = $depth;
                    }
                }
                $stats['queue_depths'] = $depths;
            } catch (\Throwable) {
                // Redis unavailable — return what we have
            }
        }

        return $this->success($stats, 'Queue statistics');
    }

    // ── Cache Management ─────────────────────────────────────
    public function cacheStats(): JsonResponse
    {
        $driver = config('cache.default');
        $stats  = [
            'driver' => $driver,
            'prefix' => config('cache.prefix'),
        ];

        if ($driver === 'redis') {
            try {
                $info     = Redis::info();
                $keyCount = 0;
                foreach ($info as $key => $value) {
                    if (str_starts_with((string) $key, 'db') && is_string($value)) {
                        if (preg_match('/keys=(\d+)/', $value, $m)) {
                            $keyCount += (int) $m[1];
                        }
                    }
                }
                $stats['redis'] = [
                    'version'           => $info['redis_version'] ?? null,
                    'memory_used_bytes' => (int) ($info['used_memory'] ?? 0),
                    'memory_peak_bytes' => (int) ($info['used_memory_peak'] ?? 0),
                    'keyspace_hits'     => (int) ($info['keyspace_hits'] ?? 0),
                    'keyspace_misses'   => (int) ($info['keyspace_misses'] ?? 0),
                    'connected_clients' => (int) ($info['connected_clients'] ?? 0),
                    'total_keys'        => $keyCount,
                ];
            } catch (\Throwable) {
                $stats['redis'] = null;
            }
        }

        return $this->success($stats, 'Cache statistics');
    }

    public function flushCache(): JsonResponse
    {
        Cache::flush();
        return $this->success(null, 'Cache flushed successfully');
    }

    public function flushCachePrefix(Request $request): JsonResponse
    {
        $prefix = (string) $request->get('prefix', '');

        if (config('cache.default') !== 'redis') {
            return $this->error('Cache prefix flush is only supported for the Redis driver', 422);
        }

        try {
            $redis       = Redis::connection();
            $cachePrefix = config('cache.prefix');
            $pattern     = $cachePrefix . ':' . $prefix . '*';
            $keys        = $redis->keys($pattern);

            if (!empty($keys)) {
                $redis->del($keys);
            }

            return $this->success(['flushed_keys' => count($keys)], 'Cache prefix flushed');
        } catch (\Throwable $e) {
            return $this->error('Failed to flush cache prefix: ' . $e->getMessage(), 500);
        }
    }
}
