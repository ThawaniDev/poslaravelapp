<?php

namespace App\Domain\IndustryRestaurant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOpenTabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'nullable|string',
            'customer_name' => 'nullable|string|max:255',
            'table_id' => 'nullable|string',
        ];
    }
}
