<?php

namespace App\Domain\BackupSync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terminal_id' => ['required', 'uuid'],
            'backup_type' => ['nullable', 'string', 'in:auto,manual,pre_update'],
            'encrypt' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
