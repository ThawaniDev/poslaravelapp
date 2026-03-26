<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityPolicyDefault extends Model
{
    use HasUuids;

    protected $table = 'security_policy_defaults';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = null;

    protected $fillable = [
        'session_timeout_minutes',
        'require_reauth_on_wake',
        'pin_min_length',
        'pin_complexity',
        'require_unique_pins',
        'pin_expiry_days',
        'biometric_enabled_default',
        'biometric_can_replace_pin',
        'max_failed_login_attempts',
        'lockout_duration_minutes',
        'failed_attempt_alert_to_owner',
        'device_registration_policy',
        'max_devices_per_store',
        'updated_by',
    ];

    protected $casts = [
        'require_reauth_on_wake' => 'boolean',
        'require_unique_pins' => 'boolean',
        'biometric_enabled_default' => 'boolean',
        'biometric_can_replace_pin' => 'boolean',
        'failed_attempt_alert_to_owner' => 'boolean',
        'session_timeout_minutes' => 'integer',
        'pin_min_length' => 'integer',
        'pin_expiry_days' => 'integer',
        'max_failed_login_attempts' => 'integer',
        'lockout_duration_minutes' => 'integer',
        'max_devices_per_store' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by');
    }
}
