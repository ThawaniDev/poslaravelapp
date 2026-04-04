<?php

namespace App\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationBatch extends Model
{
    use HasUuids;

    protected $table = 'notification_batches';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'event_key',
        'channel',
        'total_recipients',
        'sent_count',
        'failed_count',
        'status',
        'metadata',
        'started_at',
        'completed_at',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
