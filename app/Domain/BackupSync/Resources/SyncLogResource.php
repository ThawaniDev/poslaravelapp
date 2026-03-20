<?php

namespace App\Domain\BackupSync\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'store_id'     => $this->store_id,
            'terminal_id'  => $this->terminal_id,
            'direction'    => $this->direction,
            'status'       => $this->status,
            'tables_synced'=> $this->tables_synced,
            'records_pushed'  => $this->records_pushed,
            'records_pulled'  => $this->records_pulled,
            'conflicts_count' => $this->conflicts_count,
            'error_message'   => $this->error_message,
            'started_at'      => $this->started_at?->toIso8601String(),
            'completed_at'    => $this->completed_at?->toIso8601String(),
        ];
    }
}
