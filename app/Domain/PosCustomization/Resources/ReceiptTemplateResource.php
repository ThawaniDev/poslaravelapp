<?php

namespace App\Domain\PosCustomization\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'store_id'            => $this->store_id,
            'logo_url'            => $this->logo_url,
            'header_line_1'       => $this->header_line_1,
            'header_line_2'       => $this->header_line_2,
            'footer_text'         => $this->footer_text,
            'show_vat_number'     => (bool) $this->show_vat_number,
            'show_loyalty_points' => (bool) $this->show_loyalty_points,
            'show_barcode'        => (bool) $this->show_barcode,
            'paper_width_mm'      => (int) $this->paper_width_mm,
            'sync_version'        => (int) $this->sync_version,
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
