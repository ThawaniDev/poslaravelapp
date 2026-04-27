<?php

namespace App\Domain\DeliveryIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryWebhookLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'platform'           => $this->platform,
            'store_id'           => $this->store_id,
            'event_type'         => $this->event_type,
            'external_order_id'  => $this->external_order_id,
            'signature_valid'    => (bool) $this->signature_valid,
            'processed'          => (bool) $this->processed,
            'processing_result'  => $this->processing_result,
            'error_message'      => $this->error_message,
            'ip_address'         => $this->ip_address,
            // payload included only when a single record is fetched to avoid bloating list views
            'payload'            => $this->when(
                $request->route('id') !== null,
                fn () => is_string($this->payload) ? json_decode($this->payload, true) : $this->payload,
            ),
            'received_at'        => $this->received_at
                ? \Carbon\Carbon::parse($this->received_at)->toIso8601String()
                : null,
        ];
    }
}
