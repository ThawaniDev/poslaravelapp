<?php

namespace App\Domain\BackupSync\Models;

use App\Domain\BackupSync\Enums\SyncConflictResolution;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncConflict extends Model
{
    use HasUuids;

    protected $table = 'sync_conflicts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'table_name',
        'record_id',
        'conflict_type',
        'local_data',
        'cloud_data',
        'resolution',
        'auto_resolved',
        'resolution_notes',
        'resolved_by',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'resolution' => SyncConflictResolution::class,
        'local_data' => 'array',
        'cloud_data' => 'array',
        'auto_resolved' => 'boolean',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
