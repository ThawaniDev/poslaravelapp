<?php

namespace App\Domain\Security\Models;

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
        'user_agent',
        'trusted_at',
        'last_used_at',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
