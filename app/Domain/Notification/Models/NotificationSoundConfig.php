<?php

namespace App\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationSoundConfig extends Model
{
    use HasUuids;

    protected $table = 'notification_sound_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'event_key',
        'is_enabled',
        'sound_file',
        'volume',
        'repeat_count',
        'repeat_interval_seconds',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'volume' => 'decimal:2',
    ];

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
