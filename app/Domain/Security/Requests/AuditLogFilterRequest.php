<?php

namespace App\Domain\Security\Requests;

use App\Domain\Security\Enums\AuditSeverity;
use App\Domain\Security\Enums\SecurityAuditAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'      => ['required', 'uuid'],
            'action'        => ['sometimes', new Enum(SecurityAuditAction::class)],
            'severity'      => ['sometimes', new Enum(AuditSeverity::class)],
            'user_id'       => ['sometimes', 'uuid'],
            'resource_type' => ['sometimes', 'string', 'max:50'],
            'since'         => ['sometimes', 'date'],
            'per_page'      => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
