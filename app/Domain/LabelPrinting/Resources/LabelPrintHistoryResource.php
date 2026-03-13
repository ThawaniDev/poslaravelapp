<?php

namespace App\Domain\LabelPrinting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabelPrintHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'store_id'      => $this->store_id,
            'template_id'   => $this->template_id,
            'printed_by'    => $this->printed_by,
            'product_count' => (int) $this->product_count,
            'total_labels'  => (int) $this->total_labels,
            'printer_name'  => $this->printer_name,
            'printed_at'    => $this->printed_at,
        ];
    }
}
