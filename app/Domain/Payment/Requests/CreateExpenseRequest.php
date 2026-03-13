<?php

namespace App\Domain\Payment\Requests;

use App\Domain\Payment\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_session_id'   => ['nullable', 'uuid', 'exists:cash_sessions,id'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'category'          => ['required', new Enum(ExpenseCategory::class)],
            'description'       => ['nullable', 'string', 'max:500'],
            'receipt_image_url' => ['nullable', 'url', 'max:500'],
            'expense_date'      => ['nullable', 'date'],
        ];
    }
}
