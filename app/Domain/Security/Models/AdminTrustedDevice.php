<?php

namespace App\Domain\Security\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTrustedDevice extends Model
{
    use HasUuids;

    protected $table = 'admin_trusted_devices';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'device_fingerprint',
        'device_name',
        'ip_address',
        'user_agent',
        'trusted_at',
        'last_used_at',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeForAdmin(Builder $query, string $adminId): Builder
    {
        return $query->where('admin_user_id', $adminId);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function touchLastUsed(): bool
    {
        return $this->update(['last_used_at' => now()]);
    }
}
