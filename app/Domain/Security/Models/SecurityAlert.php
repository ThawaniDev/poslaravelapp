<?php

namespace App\Domain\Security\Models;

use App\Domain\PlatformAnalytics\Enums\AlertSeverity;
use App\Domain\Security\Enums\SecurityAlertStatus;
use App\Domain\Security\Enums\SecurityAlertType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    use HasUuids;

    protected $table = 'security_alerts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'alert_type',
        'severity',
        'details',
        'status',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'alert_type' => SecurityAlertType::class,
        'severity' => AlertSeverity::class,
        'status' => SecurityAlertStatus::class,
        'details' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'resolved_by');
    }
}
