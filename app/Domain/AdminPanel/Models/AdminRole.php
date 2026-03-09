<?php

namespace App\Domain\AdminPanel\Models;

use App\Domain\AdminPanel\Enums\AdminRoleSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
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
        'slug' => AdminRoleSlug::class,
        'is_system' => 'boolean',
    ];

    public function adminRolePermissions(): HasMany
    {
        return $this->hasMany(AdminRolePermission::class);
    }
    public function adminUserRoles(): HasMany
    {
        return $this->hasMany(AdminUserRole::class);
    }
}
