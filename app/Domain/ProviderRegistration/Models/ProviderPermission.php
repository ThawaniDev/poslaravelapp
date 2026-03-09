<?php

namespace App\Domain\ProviderRegistration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderPermission extends Model
{
    use HasUuids;

    protected $table = 'provider_permissions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'group',
        'description',
        'description_ar',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function defaultRoleTemplatePermissions(): HasMany
    {
        return $this->hasMany(DefaultRoleTemplatePermission::class);
    }
}
