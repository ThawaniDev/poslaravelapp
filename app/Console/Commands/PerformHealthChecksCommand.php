<?php

namespace App\Console\Commands;

use App\Domain\AdminPanel\Models\SystemHealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PerformHealthChecksCommand extends Command
{
    protected $signature   = 'infra:health-check {--service= : Run check for a specific service only}';
    protected $description = 'Run all infrastructure health checks and store results in system_health_checks';

    public function handle(): int
    {
        $services = [
            'database' => fn () => $this->checkDatabase(),
            'cache'    => fn () => $this->checkCache(),
            'queue'    => fn () => $this->checkQueue(),
            'storage'  => fn () => $this->checkStorage(),
            'spaces'   => fn () => $this->checkSpaces(),
        ];

        $targetService = $this->option('service');
        if ($targetService) {
            if (!isset($services[$targetService])) {
                $this->error("Unknown service: {$targetService}. Valid: " . implode(', ', array_keys($services)));
                return 1;
            }
            $services = [$targetService => $services[$targetService]];
        }

        $criticalCount = 0;

        foreach ($services as $service => $checker) {
            $start = microtime(true);
            try {
                $result       = $checker();
                $responseTime = (int) ((microtime(true) - $start) * 1000);

                SystemHealthCheck::create([
                    'service'          => $service,
                    'status'           => $result['status'],
                    'response_time_ms' => $responseTime,
                    'details'          => $result['details'] ?? null,
                    'error_message'    => $result['error'] ?? null,
                    'triggered_by'     => 'scheduled',
                    'checked_at'       => now(),
                ]);

                $icon = match ($result['status']) {
                    'healthy'  => '<info>✓</info>',
                    'warning'  => '<comment>⚠</comment>',
                    'critical' => '<error>✗</error>',
                    default    => '?',
                };

                $this->line("{$icon} {$service}: {$result['status']} ({$responseTime}ms)");

                if ($result['status'] === 'critical') {
                    $criticalCount++;
                }
            } catch (\Throwable $e) {
                $responseTime = (int) ((microtime(true) - $start) * 1000);

                SystemHealthCheck::create([
                    'service'          => $service,
                    'status'           => 'critical',
                    'response_time_ms' => $responseTime,
                    'error_message'    => $e->getMessage(),
                    'triggered_by'     => 'scheduled',
                    'checked_at'       => now(),
                ]);

                $this->error("✗ {$service}: critical — " . $e->getMessage());
                $criticalCount++;
            }
        }

        if ($criticalCount > 0) {
            $this->warn("{$criticalCount} service(s) in critical state.");
            return 1;
        }

        $this->info('All health checks passed.');
        return 0;
    }

    // ── Checkers ─────────────────────────────────────────────

    private function checkDatabase(): array
    {
        DB::select('SELECT 1');
        $size = DB::select("SELECT pg_database_size(current_database()) as size")[0]->size ?? 0;

        return [
            'status'  => 'healthy',
            'details' => ['db_size_mb' => round($size / 1048576, 2)],
        ];
    }

    private function checkCache(): array
    {
        $key   = 'health_check_' . uniqid();
        Cache::put($key, 'ok', 10);
        $value = Cache::get($key);
        Cache::forget($key);

        $ok = $value === 'ok';

        return [
            'status'  => $ok ? 'healthy' : 'critical',
            'details' => ['driver' => config('cache.default')],
            'error'   => $ok ? null : 'Cache read/write test failed',
        ];
    }

    private function checkQueue(): array
    {
        $pendingJobs  = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedRecent = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();

        $status = 'healthy';
        if ($failedRecent > 10 || $pendingJobs > 1000) {
            $status = 'critical';
        } elseif ($failedRecent > 0 || $pendingJobs > 100) {
            $status = 'warning';
        }

        return [
            'status'  => $status,
            'details' => [
                'pending_jobs'     => $pendingJobs,
                'failed_last_hour' => $failedRecent,
            ],
        ];
    }

    private function checkStorage(): array
    {
        $path         = storage_path();
        $freeBytes    = @disk_free_space($path) ?: 0;
        $totalBytes   = @disk_total_space($path) ?: 1;
        $usedPercent  = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);

        $status = 'healthy';
        if ($usedPercent > 95) {
            $status = 'critical';
        } elseif ($usedPercent > 85) {
            $status = 'warning';
        }

        return [
            'status'  => $status,
            'details' => [
                'free_gb'      => round($freeBytes / 1073741824, 2),
                'total_gb'     => round($totalBytes / 1073741824, 2),
                'used_percent' => $usedPercent,
            ],
        ];
    }

    private function checkSpaces(): array
    {
        try {
            $disk  = Storage::disk('spaces');
            $files = $disk->files('/');

            return [
                'status'  => 'healthy',
                'details' => ['accessible' => true, 'file_count' => count($files)],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'critical',
                'error'  => 'Spaces connectivity failed: ' . $e->getMessage(),
            ];
        }
    }
}
