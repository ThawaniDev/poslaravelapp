<?php

namespace App\Domain\AppUpdateManagement\Models;

use App\Domain\BackupSync\Enums\AppUpdateStatus;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppUpdateStat extends Model
{
    use HasUuids;

    protected $table = 'app_update_stats';
    public $incrementing = false;
    protected $keyType = 'string';

    // Both created_at and updated_at managed manually so Eloquent can read them
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'store_id',
        'app_release_id',
        'status',
        'error_message',
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        'status' => AppUpdateStatus::class,
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
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
