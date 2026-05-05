<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PinOverride extends Model
{
    use HasUuids;

    protected $table = 'pin_overrides';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'requesting_user_id',
        'authorizing_user_id',
        'permission_code',
        'action_context',
        'created_at',
    ];

    protected $casts = [
        'action_context' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function requestingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requesting_user_id');
    }
    public function authorizingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorizing_user_id');
    }
}
