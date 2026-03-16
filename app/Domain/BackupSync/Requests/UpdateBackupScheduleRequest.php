<?php

namespace App\Domain\BackupSync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'auto_backup_enabled' => ['required', 'boolean'],
            'frequency' => ['required', 'string', 'in:hourly,daily,weekly'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'encrypt_backups' => ['required', 'boolean'],
        ];
    }
}
