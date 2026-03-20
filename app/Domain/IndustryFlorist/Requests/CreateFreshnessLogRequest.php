<?php

namespace App\Domain\IndustryFlorist\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFreshnessLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'              => ['required', 'uuid'],
            'received_date'           => ['required', 'date'],
            'expected_vase_life_days' => ['required', 'integer', 'min:1'],
            'quantity'                => ['required', 'integer', 'min:1'],
            'status'                  => ['sometimes', 'string', 'in:fresh,marked_down,disposed'],
        ];
    }
}
