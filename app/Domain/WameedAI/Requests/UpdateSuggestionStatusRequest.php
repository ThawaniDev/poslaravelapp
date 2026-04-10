<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSuggestionStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:viewed,accepted,dismissed'],
        ];
    }
}
