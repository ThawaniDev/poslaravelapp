<?php

namespace App\Domain\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'notes' => $this->notes,
            'contact_person' => $this->contact_person,
            'tax_number' => $this->tax_number,
            'payment_terms' => $this->payment_terms,
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'iban' => $this->iban,
            'credit_limit' => $this->credit_limit !== null ? (float) $this->credit_limit : null,
            'outstanding_balance' => (float) ($this->outstanding_balance ?? 0),
            'rating' => $this->rating,
            'category' => $this->category,
            'is_active' => (bool) $this->is_active,
            'products_count' => $this->resource->getAttributes()['products_count']
                ?? $this->resource->getAttributes()['product_suppliers_count']
                ?? null,
            'returns_count' => $this->whenCounted('supplierReturns'),
            'purchase_orders_count' => $this->whenCounted('purchaseOrders'),
            'goods_receipts_count' => $this->whenCounted('goodsReceipts'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
