<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateImplementationFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fee_type' => 'sometimes|string|in:setup,training,custom_dev',
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string|in:invoiced,paid',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
