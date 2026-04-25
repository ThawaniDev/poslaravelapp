<?php

namespace App\Domain\ZatcaCompliance\Models;

use App\Domain\Core\Models\Store;
use App\Domain\ZatcaCompliance\Enums\ZatcaDeviceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaDevice extends Model
{
    use HasUuids;

    protected $table = 'zatca_devices';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'device_uuid',
        'hardware_serial',
        'activation_code',
        'activated_at',
        'environment',
        'status',
        'is_tampered',
        'tamper_reason',
        'current_icv',
        'current_pih',
        'certificate_id',
    ];

    protected $casts = [
        'status' => ZatcaDeviceStatus::class,
        'is_tampered' => 'boolean',
        'activated_at' => 'datetime',
        'current_icv' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(ZatcaCertificate::class, 'certificate_id');
    }
}
