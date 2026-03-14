<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateHardwareSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => 'required|uuid|exists:stores,id',
            'item_type' => 'required|string|in:terminal,printer,scanner,other',
            'item_description' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
