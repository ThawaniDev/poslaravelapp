<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'remote_wipe_requested' => 'boolean',
        'last_active_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function securityAuditLog(): HasMany
    {
        return $this->hasMany(SecurityAuditLog::class, 'device_id');
    }
}
