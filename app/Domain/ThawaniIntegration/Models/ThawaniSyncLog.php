<?php

namespace App\Domain\ThawaniIntegration\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThawaniSyncLog extends Model
{
    use HasUuids;

    protected $table = 'thawani_sync_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'entity_type',
        'entity_id',
        'action',
        'direction',
        'status',
        'request_data',
        'response_data',
        'error_message',
        'http_status_code',
        'retry_count',
        'completed_at',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
