<?php

namespace App\Domain\Receivable\Requests;

use App\Domain\Receivable\Enums\ReceivableSource;
use App\Domain\Receivable\Enums\ReceivableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receivable_type' => ['sometimes', 'string', Rule::in(array_column(ReceivableType::cases(), 'value'))],
            'source' => ['sometimes', 'string', Rule::in(array_column(ReceivableSource::cases(), 'value'))],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
