<?php

namespace App\Domain\Promotion\Requests;

use App\Domain\Promotion\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string', 'max:2000'],
            'type'                   => ['required', Rule::enum(PromotionType::class)],
            'discount_value'         => ['nullable', 'numeric', 'min:0'],
            'buy_quantity'           => ['nullable', 'integer', 'min:1'],
            'get_quantity'           => ['nullable', 'integer', 'min:1'],
            'get_discount_percent'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bundle_price'           => ['nullable', 'numeric', 'min:0'],
            'min_order_total'        => ['nullable', 'numeric', 'min:0'],
            'min_item_quantity'      => ['nullable', 'integer', 'min:1'],
            'valid_from'             => ['nullable', 'date'],
            'valid_to'               => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active_days'            => ['nullable', 'array'],
            'active_days.*'          => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'active_time_from'       => ['nullable', 'date_format:H:i'],
            'active_time_to'         => ['nullable', 'date_format:H:i'],
            'max_uses'               => ['nullable', 'integer', 'min:0'],
            'max_uses_per_customer'  => ['nullable', 'integer', 'min:0'],
            'is_stackable'           => ['nullable', 'boolean'],
            'is_active'              => ['nullable', 'boolean'],
            'is_coupon'              => ['nullable', 'boolean'],
            'product_ids'            => ['nullable', 'array'],
            'product_ids.*'          => ['uuid'],
            'category_ids'           => ['nullable', 'array'],
            'category_ids.*'         => ['uuid'],
            'customer_group_ids'     => ['nullable', 'array'],
            'customer_group_ids.*'   => ['uuid'],
            'bundle_products'        => ['nullable', 'array'],
            'bundle_products.*.product_id' => ['required_with:bundle_products', 'uuid'],
            'bundle_products.*.quantity'   => ['nullable', 'integer', 'min:1'],
        ];
    }
}
