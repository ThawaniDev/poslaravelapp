<?php

namespace App\Domain\WameedAI\Models;

use App\Domain\WameedAI\Enums\AIRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AIUsageLog extends Model
{
    use HasUuids;

    protected $table = 'ai_usage_logs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'store_id',
        'user_id',
        'ai_feature_definition_id',
        'feature_slug',
        'model_used',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'billed_cost_usd',
        'margin_percentage_applied',
        'request_payload_hash',
        'response_cached',
        'latency_ms',
        'status',
        'error_message',
        'metadata_json',
        'request_messages',
        'created_at',
    ];

    protected $casts = [
        'status' => AIRequestStatus::class,
        'response_cached' => 'boolean',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost_usd' => 'decimal:6',
        'billed_cost_usd' => 'decimal:6',
        'margin_percentage_applied' => 'decimal:3',
        'latency_ms' => 'integer',
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function featureDefinition(): BelongsTo
    {
        return $this->belongsTo(AIFeatureDefinition::class, 'ai_feature_definition_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class, 'store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'user_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(AIFeedback::class, 'ai_usage_log_id');
    }
}
