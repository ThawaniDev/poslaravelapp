<?php

namespace App\Domain\SystemConfig\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
