<?php

namespace App\Domain\DeliveryIntegration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryStatusPushLog extends Model
{
    use HasUuids;

    protected $table = 'delivery_status_push_logs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'delivery_order_mapping_id',
        'status_pushed',
        'platform',
        'http_status_code',
        'request_payload',
        'response_payload',
        'success',
        'attempt_number',
        'error_message',
        'pushed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'success' => 'boolean',
        'pushed_at' => 'datetime',
    ];

    public function deliveryOrderMapping(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderMapping::class);
    }
}
