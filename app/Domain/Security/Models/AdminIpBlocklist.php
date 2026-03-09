<?php

namespace App\Domain\Security\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminIpBlocklist extends Model
{
    use HasUuids;

    protected $table = 'admin_ip_blocklist';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_by',
    ];

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'blocked_by');
    }
}
