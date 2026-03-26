<?php

namespace App\Domain\ProviderSubscription\Models;

use App\Domain\Core\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderLimitOverride extends Model
{
    use HasUuids;

    protected $table = 'provider_limit_overrides';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'limit_key',
        'override_value',
        'reason',
        'set_by',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'set_by');
    }
}
