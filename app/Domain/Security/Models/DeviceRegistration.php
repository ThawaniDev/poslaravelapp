<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceRegistration extends Model
{
    use HasUuids;

    protected $table = 'device_registrations';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'device_name',
        'hardware_id',
        'os_info',
        'app_version',
        'last_active_at',
        'is_active',
        'remote_wipe_requested',
        'registered_at',
        'ip_address',
        'screen_resolution',
        'last_known_location',
        'device_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'remote_wipe_requested' => 'boolean',
        'last_active_at' => 'datetime',
        'registered_at' => 'datetime',
        'last_known_location' => 'array',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function securityAuditLogs(): HasMany
    {
        return $this->hasMany(SecurityAuditLog::class, 'device_id');
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeWipePending(Builder $query): Builder
    {
        return $query->where('remote_wipe_requested', true);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function requestWipe(): void
    {
        $this->update(['remote_wipe_requested' => true, 'is_active' => false]);
    }

    public function touchActivity(): void
    {
        $this->update(['last_active_at' => now()]);
    }
}
