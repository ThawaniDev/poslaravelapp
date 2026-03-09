<?php

namespace App\Domain\ThawaniIntegration\Models;

use App\Domain\ThawaniIntegration\Enums\ThawaniDeliveryType;
use App\Domain\ThawaniIntegration\Enums\ThawaniOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThawaniOrderMapping extends Model
{
    use HasUuids;

    protected $table = 'thawani_order_mappings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'order_id',
        'thawani_order_id',
        'thawani_order_number',
        'status',
        'delivery_type',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'order_total',
        'commission_amount',
        'rejection_reason',
        'accepted_at',
        'prepared_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => ThawaniOrderStatus::class,
        'delivery_type' => ThawaniDeliveryType::class,
        'order_total' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'accepted_at' => 'datetime',
        'prepared_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
