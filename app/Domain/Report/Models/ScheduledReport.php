<?php

namespace App\Domain\Report\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'store_id',
        'report_type',
        'name',
        'frequency',
        'filters',
        'recipients',
        'format',
        'last_run_at',
        'next_run_at',
        'is_active',
    ];

    protected $casts = [
        'filters' => 'array',
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];
}
