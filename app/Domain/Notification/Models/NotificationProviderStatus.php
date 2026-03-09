<?php

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationProviderStatus extends Model
{
    use HasUuids;

    protected $table = 'notification_provider_status';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'channel',
        'is_enabled',
        'priority',
        'is_healthy',
        'last_success_at',
        'last_failure_at',
        'failure_count_24h',
        'success_count_24h',
        'avg_latency_ms',
        'disabled_reason',
    ];

    protected $casts = [
        'provider' => NotificationProvider::class,
        'channel' => NotificationChannel::class,
        'is_enabled' => 'boolean',
        'is_healthy' => 'boolean',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

}
