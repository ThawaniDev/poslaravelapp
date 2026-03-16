<?php

namespace App\Domain\BackupSync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BackupListFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'backup_type' => ['nullable', 'string', 'in:auto,manual,pre_update'],
            'status' => ['nullable', 'string', 'in:completed,failed,corrupted'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
