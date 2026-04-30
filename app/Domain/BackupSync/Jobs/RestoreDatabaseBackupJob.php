<?php

namespace App\Domain\BackupSync\Jobs;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\BackupSync\Enums\DatabaseBackupStatus;
use App\Domain\BackupSync\Models\DatabaseBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

class RestoreDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 minutes
    public int $tries   = 1;

    public function __construct(
        private readonly string $backupId,
        private readonly string $triggeredByAdminId,
    ) {}

    public function handle(): void
    {
        $backup = DatabaseBackup::findOrFail($this->backupId);

        if ($backup->status !== DatabaseBackupStatus::Completed) {
            throw new \RuntimeException('Backup is not in completed state: ' . $backup->status->value);
        }

        if (empty($backup->file_path) || !file_exists($backup->file_path)) {
            throw new \RuntimeException('Backup file not found on disk: ' . $backup->file_path);
        }

        $host     = config('database.connections.pgsql.host', '127.0.0.1');
        $port     = config('database.connections.pgsql.port', '5432');
        $user     = config('database.connections.pgsql.username', 'postgres');
        $password = config('database.connections.pgsql.password', '');
        $dbName   = config('database.connections.pgsql.database', 'postgres');

        // Enable maintenance mode before restore
        Artisan::call('down', ['--secret' => 'restore-in-progress-' . substr($this->backupId, 0, 8)]);

        try {
            $result = Process::timeout(1140)
                ->env(['PGPASSWORD' => $password])
                ->run("gunzip -c {$backup->file_path} | psql -h {$host} -p {$port} -U {$user} {$dbName}");

            AdminActivityLog::create([
                'id'           => \Illuminate\Support\Str::uuid(),
                'admin_user_id' => $this->triggeredByAdminId,
                'action'       => 'restore_database_backup_completed',
                'entity_type'  => 'database_backup',
                'entity_id'    => $backup->id,
                'details'      => [
                    'success'    => $result->successful(),
                    'file_path'  => $backup->file_path,
                    'error'      => $result->successful() ? null : trim($result->errorOutput()),
                ],
                'created_at' => now(),
            ]);

            if (!$result->successful()) {
                throw new \RuntimeException('Restore failed: ' . trim($result->errorOutput()));
            }
        } finally {
            // Always bring app back up
            Artisan::call('up');
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Ensure app comes back up if the job fails
        try {
            Artisan::call('up');
        } catch (\Throwable) {
            // Ignore — app might already be up
        }

        AdminActivityLog::create([
            'id'            => \Illuminate\Support\Str::uuid(),
            'admin_user_id' => $this->triggeredByAdminId,
            'action'        => 'restore_database_backup_failed',
            'entity_type'   => 'database_backup',
            'entity_id'     => $this->backupId,
            'details'       => ['error' => $exception->getMessage()],
            'created_at'    => now(),
        ]);
    }
}
