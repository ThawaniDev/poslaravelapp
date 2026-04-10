<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AIDailyUsageSummary extends Model
{
    use HasUuids;

    protected $table = 'ai_daily_usage_summaries';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'store_id',
        'date',
        'total_requests',
        'cached_requests',
        'failed_requests',
        'total_input_tokens',
        'total_output_tokens',
        'total_estimated_cost_usd',
        'feature_breakdown_json',
        'created_at',
    ];

    protected $casts = [
        'date' => 'date',
        'total_requests' => 'integer',
        'cached_requests' => 'integer',
        'failed_requests' => 'integer',
        'total_input_tokens' => 'integer',
        'total_output_tokens' => 'integer',
        'total_estimated_cost_usd' => 'decimal:6',
        'feature_breakdown_json' => 'array',
        'created_at' => 'datetime',
    ];
}
