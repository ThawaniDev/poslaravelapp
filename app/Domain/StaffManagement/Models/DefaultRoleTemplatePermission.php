<?php

namespace App\Domain\StaffManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultRoleTemplatePermission extends Model
{
    use HasUuids;

    protected $table = 'default_role_template_permissions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'default_role_template_id',
        'provider_permission_id',
    ];

    public function defaultRoleTemplate(): BelongsTo
    {
        return $this->belongsTo(DefaultRoleTemplate::class);
    }
    public function providerPermission(): BelongsTo
    {
        return $this->belongsTo(ProviderPermission::class);
    }
}
