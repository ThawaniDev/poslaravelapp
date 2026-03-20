<?php

namespace App\Domain\PosCustomization\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuickAccessConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'store_id'     => $this->store_id,
            'grid_rows'    => (int) $this->grid_rows,
            'grid_cols'    => (int) $this->grid_cols,
            'buttons_json' => $this->buttons_json,
            'sync_version' => (int) $this->sync_version,
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
