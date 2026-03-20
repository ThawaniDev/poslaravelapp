<?php

namespace App\Domain\IndustryFlorist\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFlowerSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'             => ['required', 'uuid'],
            'arrangement_template_id' => ['required', 'uuid'],
            'frequency'               => ['required', 'string', 'in:weekly,biweekly,monthly'],
            'delivery_day'            => ['required', 'string', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'delivery_address'        => ['required', 'string', 'max:500'],
            'price_per_delivery'      => ['required', 'numeric', 'min:0'],
            'is_active'               => ['boolean'],
            'next_delivery_date'      => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
