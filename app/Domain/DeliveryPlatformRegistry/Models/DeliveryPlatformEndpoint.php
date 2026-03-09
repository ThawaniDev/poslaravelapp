<?php

namespace App\Domain\DeliveryPlatformRegistry\Models;

use App\Domain\DeliveryPlatformRegistry\Enums\DeliveryEndpointOperation;
use App\Domain\DeliveryPlatformRegistry\Enums\HttpMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPlatformEndpoint extends Model
{
    use HasUuids;

    protected $table = 'delivery_platform_endpoints';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'delivery_platform_id',
        'operation',
        'url_template',
        'http_method',
        'request_mapping',
    ];

    protected $casts = [
        'operation' => DeliveryEndpointOperation::class,
        'http_method' => HttpMethod::class,
        'request_mapping' => 'array',
    ];

    public function deliveryPlatform(): BelongsTo
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }
}
