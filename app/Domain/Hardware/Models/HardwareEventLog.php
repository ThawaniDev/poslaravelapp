<?php

namespace App\Domain\Hardware\Models;

use App\Domain\SystemConfig\Enums\HardwareDeviceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HardwareEventLog extends Model
{
    use HasUuids;

    protected $table = 'hardware_event_log';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'terminal_id',
        'device_type',
        'event',
        'details',
    ];

    protected $casts = [
        'device_type' => HardwareDeviceType::class,
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
