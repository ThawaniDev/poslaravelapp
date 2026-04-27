<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use App\Domain\Security\Enums\RoleAuditAction;
use App\Domain\StaffManagement\Models\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAuditLog extends Model
{
    use HasUuids;

    protected $table = 'role_audit_log';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'user_id',
        'action',
        'role_id',
        'details',
        'created_at',
    ];

    protected $casts = [
        'action' => RoleAuditAction::class,
        'details' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
