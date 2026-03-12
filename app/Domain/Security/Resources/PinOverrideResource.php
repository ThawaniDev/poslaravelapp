<?php

namespace App\Domain\Security\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PinOverrideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'store_id'            => $this->store_id,
            'requesting_user'     => [
                'id'   => $this->requestingUser?->id,
                'name' => $this->requestingUser?->name,
            ],
            'authorizing_user'    => [
                'id'   => $this->authorizingUser?->id,
                'name' => $this->authorizingUser?->name,
            ],
            'permission_code'     => $this->permission_code,
            'action_context'      => $this->action_context,
            'created_at'          => $this->created_at,
        ];
    }
}
