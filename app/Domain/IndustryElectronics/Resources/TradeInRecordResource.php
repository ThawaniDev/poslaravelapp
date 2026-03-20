<?php

namespace App\Domain\IndustryElectronics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeInRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'store_id'            => $this->store_id,
            'customer_id'         => $this->customer_id,
            'device_description'  => $this->device_description,
            'imei'                => $this->imei,
            'condition_grade'     => $this->condition_grade,
            'assessed_value'      => $this->assessed_value ? (float) $this->assessed_value : null,
            'applied_to_order_id' => $this->applied_to_order_id,
            'staff_user_id'       => $this->staff_user_id,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
