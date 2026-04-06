<?php

namespace App\Domain\Debit\Requests;

use App\Domain\Debit\Enums\DebitSource;
use App\Domain\Debit\Enums\DebitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDebitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'debit_type' => ['sometimes', 'string', Rule::in(array_column(DebitType::cases(), 'value'))],
            'source' => ['sometimes', 'string', Rule::in(array_column(DebitSource::cases(), 'value'))],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
