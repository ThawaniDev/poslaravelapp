<?php

namespace App\Domain\BackupSync\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'terminal_id'      => $this->terminal_id,
            'backup_type'      => $this->backup_type instanceof \BackedEnum
                ? $this->backup_type->value
                : $this->backup_type,
            'storage_location' => $this->storage_location,
            'cloud_key'        => $this->cloud_key,
            'local_path'       => $this->local_path,
            'file_size_bytes'  => (int) ($this->file_size_bytes ?? 0),
            'checksum'         => $this->checksum,
            'db_version'       => (int) ($this->db_version ?? 1),
            'records_count'    => $this->records_count !== null ? (int) $this->records_count : null,
            'is_verified'      => (bool) $this->is_verified,
            'is_encrypted'     => (bool) $this->is_encrypted,
            'status'           => $this->status instanceof \BackedEnum
                ? $this->status->value
                : $this->status,
            'error_message'    => $this->error_message,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
