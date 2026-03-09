<?php

namespace App\Domain\DeliveryIntegration\Models;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use App\Domain\DeliveryIntegration\Enums\MenuSyncStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryMenuSyncLog extends Model
{
    use HasUuids;

    protected $table = 'delivery_menu_sync_logs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'platform',
        'status',
        'items_synced',
        'items_failed',
        'error_details',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'platform' => DeliveryConfigPlatform::class,
        'status' => MenuSyncStatus::class,
        'error_details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
