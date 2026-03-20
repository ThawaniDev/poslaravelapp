<?php

namespace App\Domain\IndustryElectronics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceImeiRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'product_id'             => $this->product_id,
            'store_id'               => $this->store_id,
            'imei'                   => $this->imei,
            'imei2'                  => $this->imei2,
            'serial_number'          => $this->serial_number,
            'condition_grade'        => $this->condition_grade,
            'purchase_price'         => $this->purchase_price ? (float) $this->purchase_price : null,
            'status'                 => $this->status,
            'warranty_end_date'      => $this->warranty_end_date,
            'store_warranty_end_date'=> $this->store_warranty_end_date,
            'sold_order_id'          => $this->sold_order_id,
            'created_at'             => $this->created_at?->toIso8601String(),
        ];
    }
}
