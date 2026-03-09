<?php

namespace App\Domain\DeliveryIntegration\Models;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPlatformConfig extends Model
{
    use HasUuids;

    protected $table = 'delivery_platform_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'platform',
        'api_key',
        'merchant_id',
        'webhook_secret',
        'branch_id_on_platform',
        'is_enabled',
        'auto_accept',
        'throttle_limit',
        'last_menu_sync_at',
    ];

    protected $casts = [
        'platform' => DeliveryConfigPlatform::class,
        'is_enabled' => 'boolean',
        'auto_accept' => 'boolean',
        'last_menu_sync_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
