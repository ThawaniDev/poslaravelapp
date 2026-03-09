<?php

namespace App\Domain\Security\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminIpAllowlist extends Model
{
    use HasUuids;

    protected $table = 'admin_ip_allowlist';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'label',
        'added_by',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'added_by');
    }
}
