<?php

namespace App\Domain\Security\Models;

use App\Domain\Security\Enums\LoginAttemptType;
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
