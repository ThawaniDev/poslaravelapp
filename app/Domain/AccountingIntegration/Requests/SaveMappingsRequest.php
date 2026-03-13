<?php

namespace App\Domain\AccountingIntegration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMappingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mappings' => 'required|array|min:1',
            'mappings.*.pos_account_key' => 'required|string|max:50',
            'mappings.*.provider_account_id' => 'required|string|max:100',
            'mappings.*.provider_account_name' => 'required|string|max:255',
        ];
    }
}
