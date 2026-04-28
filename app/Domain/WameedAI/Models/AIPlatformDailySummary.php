<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AIPlatformDailySummary extends Model
{
    use HasUuids;

    protected $table = 'ai_platform_daily_summaries';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d';

    protected $fillable = [
        'date',
        'total_stores_active',
        'total_requests',
        'total_tokens',
        'total_estimated_cost_usd',
        'feature_breakdown_json',
        'top_stores_json',
        'error_rate',
        'avg_latency_ms',
        'created_at',
    ];

    protected $casts = [
        'date' => 'date',
        'total_stores_active' => 'integer',
        'total_requests' => 'integer',
        'total_tokens' => 'integer',
        'total_estimated_cost_usd' => 'decimal:6',
        'feature_breakdown_json' => 'array',
        'top_stores_json' => 'array',
        'error_rate' => 'decimal:2',
        'avg_latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];
}
