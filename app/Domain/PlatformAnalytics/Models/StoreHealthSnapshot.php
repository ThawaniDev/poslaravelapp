<?php

namespace App\Domain\PlatformAnalytics\Models;

use App\Domain\PlatformAnalytics\Enums\StoreHealthSyncStatus;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreHealthSnapshot extends Model
{
    use HasUuids;

    protected $table = 'store_health_snapshots';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d';

    protected $fillable = [
        'store_id',
        'date',
        'sync_status',
        'zatca_compliance',
        'error_count',
        'last_activity_at',
    ];

    protected $casts = [
        'sync_status' => StoreHealthSyncStatus::class,
        'zatca_compliance' => 'boolean',
        'date' => 'date',
        'last_activity_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
