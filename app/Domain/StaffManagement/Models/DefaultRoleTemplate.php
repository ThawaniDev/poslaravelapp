<?php

namespace App\Domain\StaffManagement\Models;

use App\Domain\ProviderRegistration\Models\ProviderPermission;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            ProviderPermission::class,
            'default_role_template_permissions',
            'default_role_template_id',
            'provider_permission_id',
        );
    }

    public function defaultRoleTemplatePermissions(): HasMany
    {
        return $this->hasMany(DefaultRoleTemplatePermission::class);
    }
}
