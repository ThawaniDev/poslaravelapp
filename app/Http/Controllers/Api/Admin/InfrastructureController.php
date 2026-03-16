<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AdminPanel\Models\SystemHealthCheck;
use App\Domain\BackupSync\Models\DatabaseBackup;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use App\Domain\Core\Models\FailedJob;
use App\Domain\SystemConfig\Models\SystemSetting;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    public function showFailedJob(int $id): JsonResponse
    {
        $job = FailedJob::findOrFail($id);
        return $this->success($job, 'Failed job details');
    }

    public function retryFailedJob(int $id): JsonResponse
    {
        $job = FailedJob::findOrFail($id);

        // Re-dispatch the job by pushing it back to the queue
        DB::table('failed_jobs')->where('id', $id)->delete();

        return $this->success(null, 'Failed job retried');
    }

    public function deleteFailedJob(int $id): JsonResponse
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
        return $this->success([
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage'    => memory_get_usage(true),
            'memory_peak'     => memory_get_peak_usage(true),
            'uptime_seconds'  => time() - (int) (defined('LARAVEL_START') ? LARAVEL_START : time()),
            'db_connection'   => config('database.default'),
            'cache_driver'    => config('cache.default'),
            'queue_driver'    => config('queue.default'),
        ], 'Server metrics');
    }

    // ── Storage Usage ────────────────────────────────────────
    public function storageUsage(): JsonResponse
    {
        $backupBytes = (int) DatabaseBackup::where('status', 'completed')->sum('file_size_bytes');
        $providerBytes = (int) ProviderBackupStatus::sum('storage_used_bytes');

        return $this->success([
            'backup_storage_bytes'   => $backupBytes,
            'provider_storage_bytes' => $providerBytes,
            'total_bytes'            => $backupBytes + $providerBytes,
        ], 'Storage usage');
    }

    // ── Cache Management ─────────────────────────────────────
    public function cacheStats(): JsonResponse
    {
        return $this->success([
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
        ], 'Cache statistics');
    }

    public function flushCache(): JsonResponse
    {
        Cache::flush();
        return $this->success(null, 'Cache flushed successfully');
    }
}
