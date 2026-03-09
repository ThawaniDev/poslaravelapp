<?php

namespace App\Domain\Security\Models;

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
        'two_fa_verified',
        'last_activity_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'two_fa_verified' => 'boolean',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
