<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SpendingPatternRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
        ];
    }
}
