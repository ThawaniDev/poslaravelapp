<?php

namespace App\Domain\BackupSync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPushRequest extends FormRequest
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
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.table' => ['required', 'string', 'max:100'],
            'changes.*.records' => ['required', 'array'],
            'changes.*.records.*.id' => ['required', 'uuid'],
            'changes.*.records.*._conflict' => ['nullable', 'boolean'],
            'changes.*.records.*._cloud_data' => ['nullable', 'array'],
        ];
    }
}
