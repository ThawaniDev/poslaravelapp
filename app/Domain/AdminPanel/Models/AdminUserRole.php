<?php

namespace App\Domain\AdminPanel\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AdminUserRole extends Pivot
{
    protected $table = 'admin_user_roles';
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'admin_role_id',
        'assigned_at',
        'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class);
    }
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_by');
    }
}
