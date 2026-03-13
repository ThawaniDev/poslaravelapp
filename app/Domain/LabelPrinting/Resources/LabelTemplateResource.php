<?php

namespace App\Domain\LabelPrinting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabelTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'organization_id'  => $this->organization_id,
            'name'             => $this->name,
            'label_width_mm'   => (float) $this->label_width_mm,
            'label_height_mm'  => (float) $this->label_height_mm,
            'layout_json'      => $this->layout_json,
            'is_preset'        => (bool) $this->is_preset,
            'is_default'       => (bool) $this->is_default,
            'created_by'       => $this->created_by,
            'sync_version'     => $this->sync_version,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
