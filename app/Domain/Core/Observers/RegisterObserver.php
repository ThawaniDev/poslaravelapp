<?php

namespace App\Domain\Core\Observers;

use App\Domain\Core\Models\Register;
use App\Domain\Security\Enums\AuditResourceType;
use App\Domain\Security\Enums\AuditSeverity;
use App\Domain\Security\Enums\SecurityAuditAction;
use App\Domain\Security\Services\SecurityService;
use Illuminate\Support\Facades\Log;

class RegisterObserver
{
    public function __construct(
        private readonly SecurityService $security,
    ) {}

    public function updated(Register $register): void
    {
        if (! $register->isDirty('edfapay_token')) {
            return;
        }

        try {
            $this->security->recordAudit([
                'store_id'      => $register->store_id,
                'user_id'       => null, // system-level or background; no authenticated user in observer scope
                'action'        => SecurityAuditAction::TerminalCredentialUpdate,
                'resource_type' => AuditResourceType::Terminal,
                'resource_id'   => $register->id,
                'severity'      => AuditSeverity::Warning,
                'details'       => [
                    'register_name' => $register->name,
                    'register_code' => $register->code,
                    'change'        => 'edfapay_token updated',
                    'had_token'     => $register->getOriginal('edfapay_token') !== null,
                    'token_set'     => $register->edfapay_token !== null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write audit log for edfapay_token change', [
                'register_id' => $register->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
