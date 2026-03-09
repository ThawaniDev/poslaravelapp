<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\ThawaniIntegration\Enums\ThawaniConnectionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ThawaniMarketplaceConfig extends Model
{
    use HasUuids;

    protected $table = 'thawani_marketplace_config';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'client_id_encrypted',
        'client_secret_encrypted',
        'redirect_url',
        'api_base_url',
        'api_version',
        'webhook_url',
        'webhook_secret_encrypted',
        'sync_interval_minutes',
        'is_active',
        'last_connection_at',
        'connection_status',
    ];

    protected $casts = [
        'connection_status' => ThawaniConnectionStatus::class,
        'is_active' => 'boolean',
        'last_connection_at' => 'datetime',
    ];

}
