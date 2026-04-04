<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecuritySession extends Model
{
    use HasUuids;

    protected $table = 'security_sessions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'user_id',
        'device_id',
        'session_type',
        'status',
        'ip_address',
        'user_agent',
        'started_at',
        'last_activity_at',
        'ended_at',
        'end_reason',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function heartbeat(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function end(string $reason = 'manual'): void
    {
        $this->update([
            'status' => 'ended',
            'ended_at' => now(),
            'end_reason' => $reason,
        ]);
    }
}
