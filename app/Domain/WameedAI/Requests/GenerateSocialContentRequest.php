<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSocialContentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'platform'     => ['required', 'string', 'in:instagram,twitter,snapchat,tiktok'],
            'topic'        => ['required', 'string', 'max:500'],
            'product_ids'  => ['nullable', 'array'],
            'product_ids.*' => ['uuid'],
        ];
    }
}
