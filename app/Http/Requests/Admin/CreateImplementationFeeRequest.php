<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateImplementationFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => 'required|uuid|exists:stores,id',
            'fee_type' => 'required|string|in:setup,training,custom_dev',
            'amount' => 'required|numeric|min:0',
            'status' => 'sometimes|string|in:invoiced,paid',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
