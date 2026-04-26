<?php

namespace App\Domain\BackupSync\Models;

use App\Domain\BackupSync\Enums\SyncDirection;
use App\Domain\BackupSync\Enums\SyncLogStatus;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasUuids;

    protected $table = 'sync_log';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'terminal_id',
        'direction',
        'records_count',
        'conflicts_count',
        'duration_ms',
        'status',
        'sync_token',
        'client_version',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'direction' => SyncDirection::class,
        'status' => SyncLogStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
