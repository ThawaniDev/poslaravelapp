<?php

namespace App\Domain\AccountingIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountingExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at;
        $completedAt = $this->completed_at;

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'provider' => is_string($this->provider) ? $this->provider : $this->provider->value,
            'start_date' => $this->start_date instanceof \DateTimeInterface ? $this->start_date->format('Y-m-d') : $this->start_date,
            'end_date' => $this->end_date instanceof \DateTimeInterface ? $this->end_date->format('Y-m-d') : $this->end_date,
            'export_types' => $this->export_types,
            'status' => is_string($this->status) ? $this->status : $this->status->value,
            'entries_count' => (int) $this->entries_count,
            'error_message' => $this->error_message,
            'journal_entry_ids' => $this->journal_entry_ids,
            'csv_url' => $this->csv_url,
            'triggered_by' => is_string($this->triggered_by) ? $this->triggered_by : $this->triggered_by->value,
            'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->toIso8601String() : $createdAt,
            'completed_at' => $completedAt instanceof \DateTimeInterface ? $completedAt->toIso8601String() : $completedAt,
        ];
    }
}
