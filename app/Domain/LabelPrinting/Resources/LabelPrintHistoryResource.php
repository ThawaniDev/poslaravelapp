<?php

namespace App\Domain\LabelPrinting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabelPrintHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'template_id'      => $this->template_id,
            'template_name'    => $this->whenLoaded('template', fn () => $this->template?->name),
            'printed_by'       => $this->printed_by,
            'printed_by_name'  => $this->whenLoaded('printedBy', fn () => $this->printedBy?->name),
            'product_count'    => (int) $this->product_count,
            'total_labels'     => (int) $this->total_labels,
            'printer_name'     => $this->printer_name,
            'printer_language' => $this->printer_language,
            'job_pages'        => $this->job_pages !== null ? (int) $this->job_pages : null,
            'duration_ms'      => $this->duration_ms !== null ? (int) $this->duration_ms : null,
            'printed_at'       => $this->printed_at?->toIso8601String(),
        ];
    }
}
