<?php

namespace App\Domain\BackupSync\Jobs;

use App\Domain\BackupSync\Enums\ProviderBackupStatusEnum;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckProviderBackupStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        ProviderBackupStatus::query()->chunkById(100, function ($statuses) {
            foreach ($statuses as $status) {
                $newStatus = $this->resolveStatus($status->last_successful_sync);

                if ($status->status !== $newStatus) {
                    $status->update(['status' => $newStatus]);
                }
            }
        });
    }

    private function resolveStatus(?\Illuminate\Support\Carbon $lastSync): ProviderBackupStatusEnum
    {
        if ($lastSync === null) {
            return ProviderBackupStatusEnum::Unknown;
        }

        $hoursAgo = $lastSync->diffInHours(now());

        if ($hoursAgo < 24) {
            return ProviderBackupStatusEnum::Healthy;
        }

        if ($hoursAgo < 72) {
            return ProviderBackupStatusEnum::Warning;
        }

        return ProviderBackupStatusEnum::Critical;
    }
}
