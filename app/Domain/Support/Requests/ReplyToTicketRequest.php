<?php

namespace App\Domain\Support\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyToTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_text'     => ['required', 'string', 'max:5000'],
            'attachments'      => ['nullable', 'array'],
            'attachments.*'    => ['string', 'max:500'],
            'is_internal_note' => ['sometimes', 'boolean'],
        ];
    }
}
