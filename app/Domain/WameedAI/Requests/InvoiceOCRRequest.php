<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceOCRRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'image' => ['required', 'string', 'min:100', 'max:10000000'], // base64 encoded image, ~7.5MB max
        ];
    }
}
