<?php

namespace App\Domain\DeliveryIntegration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeliveryWebhookLog extends Model
{
    use HasUuids;

    protected $table = 'delivery_webhook_logs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'platform',
        'store_id',
        'event_type',
        'external_order_id',
        'payload',
        'headers',
        'signature_valid',
        'processed',
        'processing_result',
        'error_message',
        'ip_address',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'signature_valid' => 'boolean',
        'processed' => 'boolean',
        'received_at' => 'datetime',
    ];
}
