<?php

namespace App\Domain\IndustryPharmacy\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'store_id'               => $this->store_id,
            'order_id'               => $this->order_id,
            'prescription_number'    => $this->prescription_number,
            'patient_name'           => $this->patient_name,
            'patient_id'             => $this->patient_id,
            'doctor_name'            => $this->doctor_name,
            'doctor_license'         => $this->doctor_license,
            'insurance_provider'     => $this->insurance_provider,
            'insurance_claim_amount' => $this->insurance_claim_amount,
            'notes'                  => $this->notes,
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
