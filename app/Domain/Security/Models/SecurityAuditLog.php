<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Security\Enums\AuditResourceType;
use App\Domain\Security\Enums\AuditSeverity;
use App\Domain\Security\Enums\AuditUserType;
use App\Domain\Security\Enums\SecurityAuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    use HasUuids;

    protected $table = 'security_audit_log';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'user_id',
        'user_type',
        'action',
        'resource_type',
        'resource_id',
        'details',
        'severity',
        'ip_address',
        'device_id',
        'created_at',
    ];

    protected $casts = [
        'user_type' => AuditUserType::class,
        'action' => SecurityAuditAction::class,
        'resource_type' => AuditResourceType::class,
        'severity' => AuditSeverity::class,
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceRegistration::class, 'device_id');
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByAction(Builder $query, string|SecurityAuditAction $action): Builder
    {
        $value = $action instanceof SecurityAuditAction ? $action->value : $action;
        return $query->where('action', $value);
    }

    public function scopeBySeverity(Builder $query, string|AuditSeverity $severity): Builder
    {
        $value = $severity instanceof AuditSeverity ? $severity->value : $severity;
        return $query->where('severity', $value);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
