<?php

namespace App\Domain\StaffManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DefaultRoleTemplate extends Model
{
    use HasUuids;

    protected $table = 'default_role_templates';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'description',
        'description_ar',
    ];

    public function defaultRoleTemplatePermissions(): HasMany
    {
        return $this->hasMany(DefaultRoleTemplatePermission::class);
    }
}
