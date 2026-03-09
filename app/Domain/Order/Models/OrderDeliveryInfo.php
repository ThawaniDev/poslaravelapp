<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\OrderDeliveryPlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDeliveryInfo extends Model
{
    use HasUuids;

    protected $table = 'order_delivery_info';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'platform',
        'driver_name',
        'driver_phone',
        'estimated_delivery',
        'actual_delivery',
        'delivery_fee',
        'tracking_url',
    ];

    protected $casts = [
        'platform' => OrderDeliveryPlatform::class,
        'delivery_fee' => 'decimal:2',
        'estimated_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
