<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Security\Enums\AuditResourceType;
use App\Domain\Security\Enums\AuditSeverity;
use App\Domain\Security\Enums\AuditUserType;
use App\Domain\Security\Enums\SecurityAuditAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    use HasUuids;

    protected $table = 'security_audit_log';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'user_id',
        'user_type',
        'action',
        'resource_type',
        'resource_id',
        'details',
        'severity',
        'ip_address',
        'device_id',
        'created_at',
    ];

    protected $casts = [
        'user_type' => AuditUserType::class,
        'action' => SecurityAuditAction::class,
        'resource_type' => AuditResourceType::class,
        'severity' => AuditSeverity::class,
        'details' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceRegistration::class, 'device_id');
    }
}
