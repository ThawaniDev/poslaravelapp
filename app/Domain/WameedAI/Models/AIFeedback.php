<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIFeedback extends Model
{
    use HasUuids;

    protected $table = 'ai_feedback';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ai_usage_log_id',
        'store_id',
        'user_id',
        'rating',
        'feedback_text',
        'is_helpful',
        'created_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_helpful' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function usageLog(): BelongsTo
    {
        return $this->belongsTo(AIUsageLog::class, 'ai_usage_log_id');
    }
}
