<?php

namespace App\Domain\BackupSync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terminal_id' => ['required', 'uuid'],
            'sync_token' => ['nullable', 'string'],
            'tables' => ['nullable', 'array'],
            'tables.*' => ['string', 'max:100'],
        ];
    }
}
