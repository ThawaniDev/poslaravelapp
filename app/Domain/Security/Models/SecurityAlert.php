<?php

namespace App\Domain\Security\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Security\Enums\AlertSeverity;
use App\Domain\Security\Enums\SecurityAlertStatus;
use App\Domain\Security\Enums\SecurityAlertType;
use Illuminate\Database\Eloquent\Builder;
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
        'description',
        'ip_address',
        'details',
        'status',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'created_at',
    ];

    protected $casts = [
        'alert_type' => SecurityAlertType::class,
        'severity' => AlertSeverity::class,
        'status' => SecurityAlertStatus::class,
        'details' => 'array',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'resolved_by');
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['new', 'investigating']);
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isResolved(): bool
    {
        return $this->status === SecurityAlertStatus::Resolved;
    }

    public function resolve(string $adminId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $adminId,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function startInvestigation(): void
    {
        $this->update(['status' => 'investigating']);
    }
}
