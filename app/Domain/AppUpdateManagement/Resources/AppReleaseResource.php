<?php

namespace App\Domain\AppUpdateManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'version_number'        => $this->version_number,
            'platform'              => $this->platform,
            'channel'               => $this->channel,
            'download_url'          => $this->download_url,
            'store_url'             => $this->store_url,
            'build_number'          => $this->build_number ? (int) $this->build_number : null,
            'submission_status'     => $this->submission_status,
            'release_notes'         => $this->release_notes,
            'release_notes_ar'      => $this->release_notes_ar,
            'is_force_update'       => (bool) $this->is_force_update,
            'min_supported_version' => $this->min_supported_version,
            'rollout_percentage'    => (int) $this->rollout_percentage,
            'is_active'             => (bool) $this->is_active,
            'released_at'           => $this->released_at?->toIso8601String(),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
