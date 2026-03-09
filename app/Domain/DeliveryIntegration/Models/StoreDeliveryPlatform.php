<?php

namespace App\Domain\DeliveryIntegration\Models;

use App\Domain\DeliveryIntegration\Enums\DeliverySyncStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDeliveryPlatform extends Model
{
    use HasUuids;

    protected $table = 'store_delivery_platforms';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'delivery_platform_id',
        'credentials',
        'inbound_api_key',
        'is_enabled',
        'sync_status',
        'last_sync_at',
        'last_error',
    ];

    protected $casts = [
        'sync_status' => DeliverySyncStatus::class,
        'credentials' => 'array',
        'is_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function deliveryPlatform(): BelongsTo
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }
}
