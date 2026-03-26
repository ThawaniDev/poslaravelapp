<?php

namespace App\Domain\AdminPanel\Models;

use App\Domain\AdminPanel\Enums\AdminRoleSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRole extends Model
{
    use HasUuids;

    protected $table = 'admin_roles';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminPermission::class,
            'admin_role_permissions',
            'admin_role_id',
            'admin_permission_id',
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminUser::class,
            'admin_user_roles',
            'admin_role_id',
            'admin_user_id',
        )->withPivot('assigned_at', 'assigned_by');
    }

    public function adminRolePermissions(): HasMany
    {
        return $this->hasMany(AdminRolePermission::class);
    }
    public function adminUserRoles(): HasMany
    {
        return $this->hasMany(AdminUserRole::class);
    }
}
