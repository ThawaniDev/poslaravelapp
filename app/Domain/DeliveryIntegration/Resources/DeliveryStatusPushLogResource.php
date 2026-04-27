<?php

namespace App\Domain\DeliveryIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryStatusPushLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                           => $this->id,
            'delivery_order_mapping_id'    => $this->delivery_order_mapping_id,
            'platform'                     => $this->platform,
            'status_pushed'                => $this->status_pushed,
            'http_status_code'             => $this->http_status_code ? (int) $this->http_status_code : null,
            'success'                      => (bool) $this->success,
            'attempt_number'               => (int) ($this->attempt_number ?? 1),
            'error_message'                => $this->error_message,
            'request_payload'              => is_string($this->request_payload)
                ? json_decode($this->request_payload, true)
                : $this->request_payload,
            'response_payload'             => is_string($this->response_payload)
                ? json_decode($this->response_payload, true)
                : $this->response_payload,
            'pushed_at'                    => $this->pushed_at
                ? \Carbon\Carbon::parse($this->pushed_at)->toIso8601String()
                : null,
        ];
    }
}
