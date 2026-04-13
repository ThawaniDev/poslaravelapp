<?php

namespace App\Domain\BackupSync\Models;

use App\Domain\BackupSync\Enums\DatabaseBackupStatus;
use App\Domain\BackupSync\Enums\DatabaseBackupType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DatabaseBackup extends Model
{
    use HasUuids;

    protected $table = 'database_backups';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'backup_type',
        'file_path',
        'file_size_bytes',
        'tables_count',
        'rows_count',
        'checksum',
        'status',
        'error_message',
        'triggered_by',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'backup_type' => DatabaseBackupType::class,
        'status' => DatabaseBackupStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function triggeredByAdmin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domain\AdminPanel\Models\AdminUser::class, 'triggered_by');
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }
        return $this->started_at->diffForHumans($this->completed_at, true);
    }

}
