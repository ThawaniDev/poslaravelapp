<?php

namespace App\Domain\IndustryRestaurant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_number' => ['required', 'string', 'max:20'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'seats'        => ['required', 'integer', 'min:1', 'max:50'],
            'zone'         => ['nullable', 'string', 'max:100'],
            'position_x'   => ['nullable', 'numeric'],
            'position_y'   => ['nullable', 'numeric'],
            'is_active'    => ['boolean'],
        ];
    }
}
