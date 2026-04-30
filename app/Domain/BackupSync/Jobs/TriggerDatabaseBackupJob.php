<?php

namespace App\Domain\BackupSync\Jobs;

use App\Domain\BackupSync\Enums\DatabaseBackupStatus;
use App\Domain\BackupSync\Models\DatabaseBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class TriggerDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries   = 1;

    public function __construct(
        private readonly string $backupId,
    ) {}

    public function handle(): void
    {
        $backup = DatabaseBackup::findOrFail($this->backupId);

        $dbName   = config('database.connections.pgsql.database', 'postgres');
        $host     = config('database.connections.pgsql.host', '127.0.0.1');
        $port     = config('database.connections.pgsql.port', '5432');
        $user     = config('database.connections.pgsql.username', 'postgres');
        $password = config('database.connections.pgsql.password', '');

        $dir      = storage_path('app/backups');
        $dumpPath = $dir . '/manual_' . $backup->id . '_' . now()->format('Ymd_His') . '.sql.gz';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $result = Process::timeout(540)
                ->env(['PGPASSWORD' => $password])
                ->run("pg_dump -h {$host} -p {$port} -U {$user} {$dbName} | gzip > {$dumpPath}");

            if ($result->successful() && file_exists($dumpPath)) {
                $tablesCount = (int) DB::selectOne(
                    "SELECT count(*) as cnt FROM information_schema.tables WHERE table_schema = 'public'"
                )?->cnt ?? 0;

                $backup->update([
                    'status'          => DatabaseBackupStatus::Completed,
                    'completed_at'    => now(),
                    'file_path'       => $dumpPath,
                    'file_size_bytes' => filesize($dumpPath),
                    'checksum'        => md5_file($dumpPath),
                    'tables_count'    => $tablesCount,
                ]);
            } else {
                $backup->update([
                    'status'        => DatabaseBackupStatus::Failed,
                    'completed_at'  => now(),
                    'error_message' => trim($result->errorOutput()) ?: 'pg_dump failed with no output',
                ]);
            }
        } catch (\Throwable $e) {
            $backup->update([
                'status'        => DatabaseBackupStatus::Failed,
                'completed_at'  => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        DatabaseBackup::where('id', $this->backupId)->update([
            'status'        => DatabaseBackupStatus::Failed,
            'completed_at'  => now(),
            'error_message' => $exception->getMessage(),
        ]);
    }
}
