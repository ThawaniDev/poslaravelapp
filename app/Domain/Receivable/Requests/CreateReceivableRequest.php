<?php

namespace App\Domain\Receivable\Requests;

use App\Domain\Receivable\Enums\ReceivableSource;
use App\Domain\Receivable\Enums\ReceivableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'receivable_type' => ['required', 'string', Rule::in(array_column(ReceivableType::cases(), 'value'))],
            'source' => ['required', 'string', Rule::in(array_column(ReceivableSource::cases(), 'value'))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'due_date' => ['nullable', 'date'],
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
            'receivable_type.required' => 'Receivable type is required.',
            'receivable_type.in' => 'Invalid receivable type.',
            'source.required' => 'Source is required.',
            'source.in' => 'Invalid source.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be at least 0.01.',
            'amount.max' => 'Amount cannot exceed 999,999.99.',
        ];
    }
}
