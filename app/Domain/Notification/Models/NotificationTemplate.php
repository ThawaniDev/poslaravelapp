<?php

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasUuids;

    protected $table = 'notification_templates';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'event_key',
        'channel',
        'title',
        'title_ar',
        'body',
        'body_ar',
        'available_variables',
        'is_active',
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'available_variables' => 'array',
        'is_active' => 'boolean',
    ];

}
