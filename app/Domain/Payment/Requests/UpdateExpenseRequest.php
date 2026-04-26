<?php

namespace App\Domain\Payment\Requests;

use App\Domain\Payment\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'            => ['sometimes', 'numeric', 'min:0.01'],
            'category'          => ['sometimes', Rule::enum(ExpenseCategory::class)],
            'description'       => ['nullable', 'string', 'max:1000'],
            'receipt_image_url' => ['nullable', 'string', 'max:2000'],
            'expense_date'      => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
