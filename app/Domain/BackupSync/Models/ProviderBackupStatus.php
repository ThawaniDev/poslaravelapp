<?php

namespace App\Domain\BackupSync\Models;

use App\Domain\Core\Models\Store;
use App\Domain\BackupSync\Enums\ProviderBackupStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderBackupStatus extends Model
{
    use HasUuids;

    protected $table = 'provider_backup_status';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'terminal_id',
        'last_successful_sync',
        'last_cloud_backup',
        'storage_used_bytes',
        'status',
    ];

    protected $casts = [
        'status' => ProviderBackupStatusEnum::class,
        'last_successful_sync' => 'datetime',
        'last_cloud_backup' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
