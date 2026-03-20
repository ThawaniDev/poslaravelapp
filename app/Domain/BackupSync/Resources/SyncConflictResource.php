<?php

namespace App\Domain\BackupSync\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncConflictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'store_id'        => $this->store_id,
            'table_name'      => $this->table_name,
            'record_id'       => $this->record_id,
            'local_data'      => $this->local_data,
            'remote_data'     => $this->remote_data,
            'resolution'      => $this->resolution,
            'resolved_by'     => $this->resolved_by,
            'resolved_at'     => $this->resolved_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
