<?php

namespace App\Domain\StaffManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomRolePackageConfig extends Model
{
    use HasUuids;

    protected $table = 'custom_role_package_config';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'subscription_plan_id',
        'is_custom_roles_enabled',
        'max_custom_roles',
    ];

    protected $casts = [
        'is_custom_roles_enabled' => 'boolean',
    ];

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
