<?php

namespace App\Domain\IndustryBakery\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_batches' => ['nullable', 'integer', 'min:0'],
            'actual_yield'   => ['nullable', 'integer', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ];
    }
}
