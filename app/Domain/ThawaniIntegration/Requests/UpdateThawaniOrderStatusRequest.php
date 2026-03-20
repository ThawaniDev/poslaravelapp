<?php

namespace App\Domain\ThawaniIntegration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThawaniOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:accepted,preparing,ready,dispatched,completed,rejected,cancelled'],
            'reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:500'],
        ];
    }
}
