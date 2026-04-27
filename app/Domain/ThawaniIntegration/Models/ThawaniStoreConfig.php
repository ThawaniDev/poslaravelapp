<?php

namespace App\Domain\ThawaniIntegration\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThawaniStoreConfig extends Model
{
    use HasUuids;

    protected $table = 'thawani_store_config';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'thawani_store_id',
        'marketplace_url',
        'api_key',
        'api_secret',
        'is_connected',
        'is_store_open',
        'store_closed_reason',
        'order_acceptance_timeout_minutes',
        'auto_sync_products',
        'auto_sync_inventory',
        'auto_accept_orders',
        'operating_hours_json',
        'commission_rate',
        'connected_at',
    ];

    protected $casts = [
        'operating_hours_json' => 'array',
        'is_connected' => 'boolean',
        'is_store_open' => 'boolean',
        'auto_sync_products' => 'boolean',
        'auto_sync_inventory' => 'boolean',
        'auto_accept_orders' => 'boolean',
        'commission_rate' => 'decimal:2',
        'order_acceptance_timeout_minutes' => 'integer',
        'connected_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
