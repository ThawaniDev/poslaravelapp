<?php

namespace App\Domain\BackupSync\Models;

use App\Domain\BackupSync\Enums\BackupHistoryStatus;
use App\Domain\BackupSync\Enums\BackupType;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupHistory extends Model
{
    use HasUuids;

    protected $table = 'backup_history';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'terminal_id',
        'backup_type',
        'storage_location',
        'local_path',
        'cloud_key',
        'file_size_bytes',
        'checksum',
        'db_version',
        'records_count',
        'is_verified',
        'is_encrypted',
        'status',
        'error_message',
    ];

    protected $casts = [
        'backup_type' => BackupType::class,
        'status' => BackupHistoryStatus::class,
        'is_verified' => 'boolean',
        'is_encrypted' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
