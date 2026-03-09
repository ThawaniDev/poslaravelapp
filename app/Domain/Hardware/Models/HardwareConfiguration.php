<?php

namespace App\Domain\Hardware\Models;

use App\Domain\Shared\Enums\ConnectionType;
use App\Domain\SystemConfig\Enums\HardwareDeviceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HardwareConfiguration extends Model
{
    use HasUuids;

    protected $table = 'hardware_configurations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'terminal_id',
        'device_type',
        'connection_type',
        'device_name',
        'config_json',
        'is_active',
    ];

    protected $casts = [
        'device_type' => HardwareDeviceType::class,
        'connection_type' => ConnectionType::class,
        'config_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
