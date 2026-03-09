<?php

namespace App\Domain\DeliveryPlatformRegistry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPlatformWebhookTemplate extends Model
{
    use HasUuids;

    protected $table = 'delivery_platform_webhook_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'delivery_platform_id',
        'path_template',
    ];

    public function deliveryPlatform(): BelongsTo
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }
}
