<?php

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\FcmDeviceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcmToken extends Model
{
    use HasUuids;

    protected $table = 'fcm_tokens';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'token',
        'device_type',
    ];

    protected $casts = [
        'device_type' => FcmDeviceType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class);
    }
}
