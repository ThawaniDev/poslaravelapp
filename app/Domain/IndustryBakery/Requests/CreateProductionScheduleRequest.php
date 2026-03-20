<?php

namespace App\Domain\IndustryBakery\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipe_id'       => ['required', 'uuid'],
            'schedule_date'   => ['required', 'date'],
            'planned_batches' => ['required', 'integer', 'min:1'],
            'planned_yield'   => ['nullable', 'integer', 'min:1'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }
}
