<?php

namespace App\Domain\IndustryBakery\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBakeryRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'         => ['nullable', 'uuid'],
            'name'               => ['required', 'string', 'max:255'],
            'expected_yield'     => ['nullable', 'string', 'max:100'],
            'prep_time_minutes'  => ['required', 'integer', 'min:0'],
            'bake_time_minutes'  => ['required', 'integer', 'min:0'],
            'bake_temperature_c' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'instructions'       => ['nullable', 'string', 'max:5000'],
        ];
    }
}
