<?php

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryLog extends Model
{
    use HasUuids;

    protected $table = 'notification_delivery_logs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'channel',
        'provider',
        'recipient',
        'status',
        'provider_message_id',
        'error_message',
        'latency_ms',
        'is_fallback',
        'attempted_providers',
        'retry_count',
        'next_retry_at',
        'request_payload',
        'response_payload',
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'status' => NotificationDeliveryStatus::class,
        'attempted_providers' => 'array',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'is_fallback' => 'boolean',
        'next_retry_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationCustom::class, 'notification_id');
    }
}
