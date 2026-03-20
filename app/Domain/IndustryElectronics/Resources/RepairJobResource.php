<?php

namespace App\Domain\IndustryElectronics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepairJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'store_id'          => $this->store_id,
            'customer_id'       => $this->customer_id,
            'device_description'=> $this->device_description,
            'imei'              => $this->imei,
            'issue_description' => $this->issue_description,
            'status'            => $this->status,
            'diagnosis_notes'   => $this->diagnosis_notes,
            'repair_notes'      => $this->repair_notes,
            'estimated_cost'    => $this->estimated_cost ? (float) $this->estimated_cost : null,
            'final_cost'        => $this->final_cost ? (float) $this->final_cost : null,
            'parts_used'        => $this->parts_used,
            'staff_user_id'     => $this->staff_user_id,
            'received_at'       => $this->received_at?->toIso8601String(),
            'estimated_ready_at'=> $this->estimated_ready_at?->toIso8601String(),
            'completed_at'      => $this->completed_at?->toIso8601String(),
            'collected_at'      => $this->collected_at?->toIso8601String(),
        ];
    }
}
