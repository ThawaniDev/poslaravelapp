<?php

namespace App\Domain\StaffManagement\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBranchAssignment extends Model
{
    use HasUuids;

    protected $table = 'staff_branch_assignments';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'staff_user_id',
        'branch_id',
        'role_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'branch_id');
    }
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
