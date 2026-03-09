<?php

namespace App\Domain\AdminPanel\Models;

use App\Domain\AdminPanel\Enums\AdminPermissionGroup;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminPermission extends Model
{
    use HasUuids;

    protected $table = 'admin_permissions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'group',
        'description',
    ];

    protected $casts = [
        'group' => AdminPermissionGroup::class,
    ];

    public function adminRolePermissions(): HasMany
    {
        return $this->hasMany(AdminRolePermission::class);
    }
}
