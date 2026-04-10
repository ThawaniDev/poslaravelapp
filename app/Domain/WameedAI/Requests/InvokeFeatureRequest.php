<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvokeFeatureRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'context'     => ['nullable', 'array'],
            'context.*'   => ['nullable', 'string', 'max:10000'],
        ];
    }
}
