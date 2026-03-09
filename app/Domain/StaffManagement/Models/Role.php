<?php

namespace App\Domain\StaffManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';
    protected $guard_name = 'staff';

    protected $fillable = [
        'store_id',
        'name',
        'display_name',
        'guard_name',
        'is_predefined',
        'description',
    ];

    protected $casts = [
        'is_predefined' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function roleHasPermissions(): HasMany
    {
        return $this->hasMany(RoleHasPermission::class);
    }
    public function modelHasRoles(): HasMany
    {
        return $this->hasMany(ModelHasRole::class);
    }
    public function roleAuditLog(): HasMany
    {
        return $this->hasMany(RoleAuditLog::class);
    }
    public function staffBranchAssignments(): HasMany
    {
        return $this->hasMany(StaffBranchAssignment::class);
    }
}
