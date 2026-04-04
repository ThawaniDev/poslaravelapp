<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityPolicy extends Model
{
    use HasUuids;

    protected $table = 'security_policies';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'pin_min_length',
        'pin_max_length',
        'auto_lock_seconds',
        'max_failed_attempts',
        'lockout_duration_minutes',
        'require_2fa_owner',
        'session_max_hours',
        'require_pin_override_void',
        'require_pin_override_return',
        'require_pin_override_discount',
        'discount_override_threshold',
        'biometric_enabled',
        'pin_expiry_days',
        'require_unique_pins',
        'max_devices',
        'audit_retention_days',
        'force_logout_on_role_change',
        'password_expiry_days',
        'require_strong_password',
        'ip_restriction_enabled',
        'allowed_ip_ranges',
    ];

    protected $casts = [
        'pin_min_length' => 'integer',
        'pin_max_length' => 'integer',
        'auto_lock_seconds' => 'integer',
        'max_failed_attempts' => 'integer',
        'lockout_duration_minutes' => 'integer',
        'session_max_hours' => 'integer',
        'require_2fa_owner' => 'boolean',
        'require_pin_override_void' => 'boolean',
        'require_pin_override_return' => 'boolean',
        'require_pin_override_discount' => 'boolean',
        'discount_override_threshold' => 'decimal:2',
        'biometric_enabled' => 'boolean',
        'pin_expiry_days' => 'integer',
        'require_unique_pins' => 'boolean',
        'max_devices' => 'integer',
        'audit_retention_days' => 'integer',
        'force_logout_on_role_change' => 'boolean',
        'password_expiry_days' => 'integer',
        'require_strong_password' => 'boolean',
        'ip_restriction_enabled' => 'boolean',
        'allowed_ip_ranges' => 'array',
    ];

    public static array $defaults = [
        'pin_min_length' => 4,
        'pin_max_length' => 6,
        'auto_lock_seconds' => 300,
        'max_failed_attempts' => 5,
        'lockout_duration_minutes' => 15,
        'require_2fa_owner' => false,
        'session_max_hours' => 12,
        'require_pin_override_void' => true,
        'require_pin_override_return' => true,
        'require_pin_override_discount' => false,
        'discount_override_threshold' => 20.00,
        'biometric_enabled' => false,
        'pin_expiry_days' => 0,
        'require_unique_pins' => false,
        'max_devices' => 10,
        'audit_retention_days' => 90,
        'force_logout_on_role_change' => true,
        'password_expiry_days' => 0,
        'require_strong_password' => false,
        'ip_restriction_enabled' => false,
        'allowed_ip_ranges' => [],
    ];

    // ─── Relationships ───────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public static function getOrCreateForStore(string $storeId): static
    {
        return static::firstOrCreate(
            ['store_id' => $storeId],
            static::$defaults,
        );
    }

    public function isLockoutActive(int $recentFailedAttempts): bool
    {
        return $recentFailedAttempts >= $this->max_failed_attempts;
    }
}
