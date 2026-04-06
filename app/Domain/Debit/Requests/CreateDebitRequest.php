<?php

namespace App\Domain\Debit\Requests;

use App\Domain\Debit\Enums\DebitSource;
use App\Domain\Debit\Enums\DebitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDebitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'debit_type' => ['required', 'string', Rule::in(array_column(DebitType::cases(), 'value'))],
            'source' => ['required', 'string', Rule::in(array_column(DebitSource::cases(), 'value'))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Selected customer does not exist.',
            'debit_type.required' => 'Debit type is required.',
            'debit_type.in' => 'Invalid debit type.',
            'source.required' => 'Source is required.',
            'source.in' => 'Invalid source.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be at least 0.01.',
            'amount.max' => 'Amount cannot exceed 999,999.99.',
        ];
    }
}
