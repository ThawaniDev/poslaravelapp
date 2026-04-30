<?php

namespace App\Domain\Support\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'    => ['required', 'string', 'in:billing,technical,zatca,feature_request,general,hardware'],
            'priority'    => ['nullable', 'string', 'in:low,medium,high,critical'],
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.url'      => ['required_with:attachments', 'string', 'max:2000'],
            'attachments.*.filename' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.size'     => ['sometimes', 'integer'],
        ];
    }
}
