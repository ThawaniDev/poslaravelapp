<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\DeliveryPlatformRegistry\Enums\DriverProtocol;
use App\Domain\SystemConfig\Enums\HardwareDeviceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CertifiedHardware extends Model
{
    use HasUuids;

    protected $table = 'certified_hardware';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'device_type',
        'brand',
        'model',
        'driver_protocol',
        'connection_types',
        'firmware_version_min',
        'paper_widths',
        'setup_instructions',
        'setup_instructions_ar',
        'is_certified',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'device_type' => HardwareDeviceType::class,
        'driver_protocol' => DriverProtocol::class,
        'connection_types' => 'array',
        'paper_widths' => 'array',
        'is_certified' => 'boolean',
        'is_active' => 'boolean',
    ];

}
