<?php

namespace App\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    use HasUuids;

    protected $table = 'notification_schedules';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'event_key',
        'channel',
        'recipient_user_id',
        'recipient_group',
        'variables',
        'schedule_type',
        'scheduled_at',
        'cron_expression',
        'timezone',
        'is_active',
        'last_sent_at',
        'next_run_at',
        'created_by',
        'category',
        'title',
        'message',
        'priority',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'scheduled_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('next_run_at', '<=', now())->where('is_active', true);
    }
}
