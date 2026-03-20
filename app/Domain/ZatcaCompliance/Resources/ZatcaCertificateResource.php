<?php

namespace App\Domain\ZatcaCompliance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZatcaCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'certificate_type' => $this->certificate_type,
            'ccsid'            => $this->ccsid,
            'issued_at'        => $this->issued_at?->toIso8601String(),
            'expires_at'       => $this->expires_at?->toIso8601String(),
            'status'           => $this->status,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
