<?php

namespace App\Domain\Promotion\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'organization_id'       => $this->organization_id,
            'name'                  => $this->name,
            'description'           => $this->description,
            'type'                  => $this->type?->value ?? $this->type,
            'discount_value'        => $this->discount_value ? (float) $this->discount_value : null,
            'buy_quantity'          => $this->buy_quantity,
            'get_quantity'          => $this->get_quantity,
            'get_discount_percent'  => $this->get_discount_percent ? (float) $this->get_discount_percent : null,
            'bundle_price'          => $this->bundle_price ? (float) $this->bundle_price : null,
            'min_order_total'       => $this->min_order_total ? (float) $this->min_order_total : null,
            'min_item_quantity'     => $this->min_item_quantity,
            'valid_from'            => $this->valid_from?->toIso8601String(),
            'valid_to'              => $this->valid_to?->toIso8601String(),
            'active_days'           => $this->active_days,
            'active_time_from'      => $this->active_time_from,
            'active_time_to'        => $this->active_time_to,
            'max_uses'              => $this->max_uses,
            'max_uses_per_customer' => $this->max_uses_per_customer,
            'is_stackable'          => (bool) $this->is_stackable,
            'is_active'             => (bool) $this->is_active,
            'is_coupon'             => (bool) $this->is_coupon,
            'usage_count'           => (int) $this->usage_count,
            'sync_version'          => $this->sync_version,
            'product_ids'           => $this->whenLoaded('promotionProducts', fn () =>
                $this->promotionProducts->pluck('product_id')->values()
            ),
            'category_ids'          => $this->whenLoaded('promotionCategories', fn () =>
                $this->promotionCategories->pluck('category_id')->values()
            ),
            'customer_group_ids'    => $this->whenLoaded('promotionCustomerGroups', fn () =>
                $this->promotionCustomerGroups->pluck('customer_group_id')->values()
            ),
            'coupon_codes'          => $this->whenLoaded('couponCodes', fn () =>
                CouponCodeResource::collection($this->couponCodes)
            ),
            'bundle_products'       => $this->whenLoaded('bundleProducts', fn () =>
                $this->bundleProducts->map(fn ($bp) => [
                    'id' => $bp->id,
                    'product_id' => $bp->product_id,
                    'quantity' => $bp->quantity,
                ])
            ),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
