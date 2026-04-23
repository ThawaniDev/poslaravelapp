<?php

namespace App\Domain\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canSeeCost = $user
            && (($user->role->value ?? null) === 'owner' || $user->can('reports.view_margin'));

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'sell_price' => (float) $this->sell_price,
            'cost_price' => $this->when(
                $canSeeCost,
                fn () => $this->cost_price !== null ? (float) $this->cost_price : null,
            ),
            'unit' => $this->unit?->value,
            'tax_rate' => $this->tax_rate !== null ? (float) $this->tax_rate : null,
            'is_weighable' => (bool) $this->is_weighable,
            'tare_weight' => $this->tare_weight !== null ? (float) $this->tare_weight : null,
            'is_active' => (bool) $this->is_active,
            'is_combo' => (bool) $this->is_combo,
            'age_restricted' => (bool) $this->age_restricted,
            'offer_price' => $this->offer_price !== null ? (float) $this->offer_price : null,
            'offer_start' => $this->offer_start?->toIso8601String(),
            'offer_end' => $this->offer_end?->toIso8601String(),
            'min_order_qty' => $this->min_order_qty !== null ? (int) $this->min_order_qty : null,
            'max_order_qty' => $this->max_order_qty !== null ? (int) $this->max_order_qty : null,
            'image_url' => $this->image_url,
            'sync_version' => $this->sync_version,

            'category' => new CategoryResource($this->whenLoaded('category')),

            'barcodes' => ProductBarcodeResource::collection($this->whenLoaded('productBarcodes')),

            'images' => ProductImageResource::collection($this->whenLoaded('productImages')),

            'variants' => ProductVariantResource::collection($this->whenLoaded('productVariants')),

            'modifier_groups' => ModifierGroupResource::collection($this->whenLoaded('modifierGroups')),

            'store_prices' => StorePriceResource::collection($this->whenLoaded('storePrices')),

            'suppliers' => ProductSupplierResource::collection($this->whenLoaded('productSuppliers')),

            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
