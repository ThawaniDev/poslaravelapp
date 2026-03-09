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

    protected $fillable = [
        'store_id',
        'app_release_id',
        'status',
        'error_message',
    ];

    protected $casts = [
        'status' => AppUpdateStatus::class,
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
