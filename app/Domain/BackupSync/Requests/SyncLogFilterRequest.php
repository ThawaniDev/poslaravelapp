<?php

namespace App\Domain\BackupSync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['nullable', 'string', 'in:push,pull,full'],
            'status' => ['nullable', 'string', 'in:success,partial,failed'],
            'terminal_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
