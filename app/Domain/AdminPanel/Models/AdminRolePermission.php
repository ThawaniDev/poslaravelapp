<?php

namespace App\Domain\AdminPanel\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AdminRolePermission extends Pivot
{
    protected $table = 'admin_role_permissions';
    public $timestamps = false;

    protected $fillable = [
        'admin_role_id',
        'admin_permission_id',
    ];

    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class);
    }
    public function adminPermission(): BelongsTo
    {
        return $this->belongsTo(AdminPermission::class);
    }
}
