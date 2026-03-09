<?php

namespace App\Domain\Security\Models;

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
    ];

    protected $casts = [
        'require_2fa_owner' => 'boolean',
        'require_pin_override_void' => 'boolean',
        'require_pin_override_return' => 'boolean',
        'require_pin_override_discount' => 'boolean',
        'discount_override_threshold' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
