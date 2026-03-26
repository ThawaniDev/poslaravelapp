<?php

namespace App\Domain\AppUpdateManagement\Models;

use App\Domain\BackupSync\Enums\AppUpdateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppUpdateStat extends Model
{
    use HasUuids;

    protected $table = 'app_update_stats';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    const CREATED_AT = null;

    protected $fillable = [
        'store_id',
        'app_release_id',
        'status',
        'error_message',
        'updated_at',
    ];

    protected $casts = [
        'status' => AppUpdateStatus::class,
        'updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function appRelease(): BelongsTo
    {
        return $this->belongsTo(AppRelease::class);
    }
}
