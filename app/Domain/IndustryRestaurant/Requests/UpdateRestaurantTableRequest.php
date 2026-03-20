<?php

namespace App\Domain\IndustryRestaurant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => 'nullable|string|max:100',
            'seats' => 'nullable|integer|min:1',
            'zone' => 'nullable|string|max:50',
            'position_x' => 'nullable|numeric',
            'position_y' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ];
    }
}
