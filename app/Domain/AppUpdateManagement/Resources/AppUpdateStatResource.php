<?php

namespace App\Domain\AppUpdateManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppUpdateStatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'store_id'       => $this->store_id,
            'app_release_id' => $this->app_release_id,
            'status'         => $this->status,
            'error_message'  => $this->error_message,
            'updated_at'     => $this->updated_at?->toIso8601String(),
            'release'        => $this->whenLoaded('appRelease', fn () => new AppReleaseResource($this->appRelease)),
        ];
    }
}
