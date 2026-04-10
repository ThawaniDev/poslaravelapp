<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFeedbackRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ai_usage_log_id' => ['required', 'uuid'],
            'rating'          => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_text'   => ['nullable', 'string', 'max:2000'],
            'is_helpful'      => ['nullable', 'boolean'],
        ];
    }
}
