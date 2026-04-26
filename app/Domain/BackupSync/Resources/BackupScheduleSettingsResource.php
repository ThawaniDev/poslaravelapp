<?php

namespace App\Domain\BackupSync\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupScheduleSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'store_id'             => $this->store_id,
            'auto_backup_enabled'  => (bool) $this->auto_backup_enabled,
            'frequency'            => $this->frequency,
            'retention_days'       => (int) $this->retention_days,
            'encrypt_backups'      => (bool) $this->encrypt_backups,
            'local_backup_enabled' => (bool) $this->local_backup_enabled,
            'cloud_backup_enabled' => (bool) $this->cloud_backup_enabled,
            'backup_hour'          => (int) $this->backup_hour,
        ];
    }
}
