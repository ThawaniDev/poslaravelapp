<?php

namespace App\Domain\StaffManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'display_name',
        'module',
        'guard_name',
        'requires_pin',
    ];

    protected $casts = [
        'requires_pin' => 'boolean',
    ];

    public function roleHasPermissions(): HasMany
    {
        return $this->hasMany(RoleHasPermission::class);
    }
}
