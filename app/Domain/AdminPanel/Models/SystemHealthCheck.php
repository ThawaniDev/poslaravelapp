<?php

namespace App\Domain\AdminPanel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SystemHealthCheck extends Model
{
    use HasUuids;

    protected $table = 'system_health_checks';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'service',
        'status',
        'response_time_ms',
        'details',
        'checked_at',
    ];

    protected $casts = [
        'details' => 'array',
        'response_time_ms' => 'integer',
        'checked_at' => 'datetime',
    ];
}
