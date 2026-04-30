<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessTypeApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'name_ar'    => $this->name_ar,
            'slug'       => $this->slug,
            'icon'       => $this->icon,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
