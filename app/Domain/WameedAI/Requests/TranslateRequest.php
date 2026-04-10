<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranslateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'texts'   => ['required', 'array', 'min:1', 'max:50'],
            'texts.*' => ['required', 'string', 'max:5000'],
            'from'    => ['nullable', 'string', 'in:ar,en'],
            'to'      => ['nullable', 'string', 'in:ar,en'],
        ];
    }
}
