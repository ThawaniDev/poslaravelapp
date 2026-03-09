<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Enums\RegisterPlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Register extends Model
{
    use HasUuids;

    protected $table = 'registers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'name',
        'device_id',
        'app_version',
        'platform',
        'last_sync_at',
        'is_online',
        'is_active',
    ];

    protected $casts = [
        'platform' => RegisterPlatform::class,
        'is_online' => 'boolean',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class);
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    public function heldCarts(): HasMany
    {
        return $this->hasMany(HeldCart::class);
    }
}
