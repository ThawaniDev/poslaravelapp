<?php

namespace App\Domain\Security\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Security\Enums\LoginAttemptType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempt extends Model
{
    use HasUuids;

    protected $table = 'login_attempts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'user_identifier',
        'attempt_type',
        'is_successful',
        'ip_address',
        'device_id',
        'attempted_at',
    ];

    protected $casts = [
        'attempt_type' => LoginAttemptType::class,
        'is_successful' => 'boolean',
        'attempted_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceRegistration::class, 'device_id');
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('is_successful', true);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('is_successful', false);
    }

    public function scopeByType(Builder $query, string|LoginAttemptType $type): Builder
    {
        $value = $type instanceof LoginAttemptType ? $type->value : $type;
        return $query->where('attempt_type', $value);
    }

    public function scopeRecent(Builder $query, int $minutes = 15): Builder
    {
        return $query->where('attempted_at', '>=', now()->subMinutes($minutes));
    }
}
