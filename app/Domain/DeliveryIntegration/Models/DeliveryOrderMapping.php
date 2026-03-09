<?php

namespace App\Domain\DeliveryIntegration\Models;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderMapping extends Model
{
    use HasUuids;

    protected $table = 'delivery_order_mappings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'platform',
        'external_order_id',
        'external_status',
        'commission_amount',
        'commission_percent',
        'raw_payload',
    ];

    protected $casts = [
        'platform' => DeliveryConfigPlatform::class,
        'raw_payload' => 'array',
        'commission_amount' => 'decimal:2',
        'commission_percent' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
