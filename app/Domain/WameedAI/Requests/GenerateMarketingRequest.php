<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateMarketingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'         => ['required', 'string', 'in:sms,whatsapp'],
            'context'      => ['required', 'array'],
            'context.goal' => ['required', 'string', 'max:500'],
        ];
    }
}
