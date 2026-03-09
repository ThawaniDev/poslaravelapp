<?php

namespace App\Domain\AppUpdateManagement\Models;

use App\Domain\BackupSync\Enums\AppReleaseChannel;
use App\Domain\BackupSync\Enums\AppReleasePlatform;
use App\Domain\BackupSync\Enums\AppSubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppRelease extends Model
{
    use HasUuids;

    protected $table = 'app_releases';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'version_number',
        'platform',
        'channel',
        'download_url',
        'store_url',
        'build_number',
        'submission_status',
        'release_notes',
        'release_notes_ar',
        'is_force_update',
        'min_supported_version',
        'rollout_percentage',
        'is_active',
        'released_at',
    ];

    protected $casts = [
        'platform' => AppReleasePlatform::class,
        'channel' => AppReleaseChannel::class,
        'submission_status' => AppSubmissionStatus::class,
        'is_force_update' => 'boolean',
        'is_active' => 'boolean',
        'released_at' => 'datetime',
    ];

    public function appUpdateStats(): HasMany
    {
        return $this->hasMany(AppUpdateStat::class);
    }
}
