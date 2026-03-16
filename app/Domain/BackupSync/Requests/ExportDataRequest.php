<?php

namespace App\Domain\BackupSync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['string', 'max:100'],
            'format' => ['nullable', 'string', 'in:json,csv'],
            'include_images' => ['nullable', 'boolean'],
        ];
    }
}
