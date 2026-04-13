<?php

namespace App\Domain\Security\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminIpAllowlist extends Model
{
    use HasUuids;

    protected $table = 'admin_ip_allowlist';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'is_cidr',
        'label',
        'description',
        'last_used_at',
        'expires_at',
        'added_by',
        'created_at',
    ];

    protected $casts = [
        'is_cidr' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'added_by');
    }
}
