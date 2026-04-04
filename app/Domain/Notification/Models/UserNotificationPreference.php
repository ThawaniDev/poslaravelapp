<?php

namespace App\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    use HasUuids;

    protected $table = 'notification_preferences';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'user_id',
        'preferences_json',
        'quiet_hours_start',
        'quiet_hours_end',
        'per_category_channels',
        'sound_enabled',
        'email_digest',
        'updated_at',
    ];

    protected $casts = [
        'preferences_json' => 'array',
        'per_category_channels' => 'array',
        'sound_enabled' => 'boolean',
        'updated_at' => 'datetime',
    ];
}
