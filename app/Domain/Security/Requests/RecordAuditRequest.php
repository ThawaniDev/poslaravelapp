<?php

namespace App\Domain\Security\Requests;

use App\Domain\Security\Enums\AuditResourceType;
use App\Domain\Security\Enums\AuditSeverity;
use App\Domain\Security\Enums\AuditUserType;
use App\Domain\Security\Enums\SecurityAuditAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RecordAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'uuid'],
            'user_id' => ['sometimes', 'uuid'],
            'user_type' => ['required', new Enum(AuditUserType::class)],
            'action' => ['required', new Enum(SecurityAuditAction::class)],
            'resource_type' => ['sometimes', new Enum(AuditResourceType::class)],
            'resource_id' => ['sometimes', 'string', 'max:255'],
            'details' => ['sometimes', 'array'],
            'severity' => ['required', new Enum(AuditSeverity::class)],
            'ip_address' => ['sometimes', 'string', 'max:45'],
            'device_id' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
