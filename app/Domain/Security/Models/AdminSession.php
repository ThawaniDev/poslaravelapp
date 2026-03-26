<?php

namespace App\Domain\Security\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminSession extends Model
{
    use HasUuids;

    protected $table = 'admin_sessions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'session_token_hash',
        'ip_address',
        'user_agent',
        'status',
        'two_fa_verified',
        'started_at',
        'last_activity_at',
        'expires_at',
        'ended_at',
        'revoked_at',
    ];

    protected $casts = [
        'two_fa_verified' => 'boolean',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNull('revoked_at')
            ->whereNull('ended_at');
    }

    public function scopeForAdmin(Builder $query, string $adminId): Builder
    {
        return $query->where('admin_user_id', $adminId);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->revoked_at === null
            && $this->ended_at === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function revoke(): void
    {
        $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'ended_at' => now(),
        ]);
    }
}
