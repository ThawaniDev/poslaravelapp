<?php

namespace App\Domain\ZatcaCompliance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZatcaInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'store_id'               => $this->store_id,
            'order_id'               => $this->order_id,
            'invoice_number'         => $this->invoice_number,
            'invoice_type'           => $this->invoice_type,
            'invoice_hash'           => $this->invoice_hash,
            'qr_code_data'           => $this->qr_code_data,
            'total_amount'           => (float) $this->total_amount,
            'vat_amount'             => (float) $this->vat_amount,
            'submission_status'      => $this->submission_status,
            'zatca_response_code'    => $this->zatca_response_code,
            'zatca_response_message' => $this->zatca_response_message,
            'submitted_at'           => $this->submitted_at?->toIso8601String(),
            'created_at'             => $this->created_at?->toIso8601String(),
        ];
    }
}
