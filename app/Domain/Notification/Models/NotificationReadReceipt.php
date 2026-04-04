<?php

namespace App\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationReadReceipt extends Model
{
    use HasUuids;

    protected $table = 'notification_read_receipts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'user_id',
        'read_at',
        'read_via',
        'device_type',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function notification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NotificationCustom::class, 'notification_id');
    }
}
