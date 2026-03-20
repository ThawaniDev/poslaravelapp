<?php

namespace App\Domain\IndustryFlorist\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFlowerSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'frequency'           => ['sometimes', 'string', 'in:weekly,biweekly,monthly'],
            'delivery_day'        => ['sometimes', 'string', 'max:20'],
            'delivery_address'    => ['sometimes', 'string', 'max:500'],
            'price_per_delivery'  => ['sometimes', 'numeric', 'min:0'],
            'next_delivery_date'  => ['sometimes', 'date'],
        ];
    }
}
