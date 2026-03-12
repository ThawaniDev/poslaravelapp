<?php

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    use HasUuids;

    protected $table = 'user_devices';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'platform',
        'os_version',
        'app_version',
        'fcm_token',
        'last_active_at',
        'is_trusted',
    ];

    protected $casts = [
        'platform' => DevicePlatform::class,
        'last_active_at' => 'datetime',
        'is_trusted' => 'boolean',
    ];

    // ─── Relationships ───────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    public function touchLastActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }

    public function isTrusted(): bool
    {
        return $this->is_trusted === true;
    }
}
