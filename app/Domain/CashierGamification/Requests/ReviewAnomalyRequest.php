<?php

namespace App\Domain\CashierGamification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewAnomalyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
