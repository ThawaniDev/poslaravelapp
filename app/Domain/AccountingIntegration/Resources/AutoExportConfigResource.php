<?php

namespace App\Domain\AccountingIntegration\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AutoExportConfigResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'enabled'          => $this->enabled,
            'frequency'        => $this->frequency?->value,
            'day_of_week'      => $this->day_of_week,
            'day_of_month'     => $this->day_of_month,
            'time'             => $this->time,
            'export_types'     => $this->export_types,
            'notify_email'     => $this->notify_email,
            'retry_on_failure' => $this->retry_on_failure,
            'last_run_at'      => $this->last_run_at?->toIso8601String(),
            'next_run_at'      => $this->next_run_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
