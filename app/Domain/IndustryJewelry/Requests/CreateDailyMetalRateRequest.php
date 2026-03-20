<?php

namespace App\Domain\IndustryJewelry\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDailyMetalRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'metal_type'            => ['required', 'string', 'in:gold,silver,platinum,palladium'],
            'karat'                 => ['required', 'integer', 'in:8,9,10,14,18,21,22,24'],
            'rate_per_gram'         => ['required', 'numeric', 'min:0'],
            'buyback_rate_per_gram' => ['required', 'numeric', 'min:0'],
            'effective_date'        => ['required', 'date'],
        ];
    }
}
