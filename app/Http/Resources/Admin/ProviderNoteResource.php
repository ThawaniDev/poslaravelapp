<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'admin_user_id' => $this->admin_user_id,
            'note_text' => $this->note_text,
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
        ];
    }
}
