<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductDescriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid'],
            'tone'       => ['nullable', 'string', 'in:professional,casual,luxury,friendly'],
            'language'   => ['nullable', 'string', 'in:ar,en,both'],
        ];
    }
}
