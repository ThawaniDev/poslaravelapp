<?php

namespace App\Domain\Security\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminIpBlocklist extends Model
{
    use HasUuids;

    protected $table = 'admin_ip_blocklist';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'is_cidr',
        'reason',
        'hit_count',
        'last_hit_at',
        'source',
        'blocked_by',
        'blocked_at',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'is_cidr' => 'boolean',
        'hit_count' => 'integer',
        'last_hit_at' => 'datetime',
        'blocked_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'blocked_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
