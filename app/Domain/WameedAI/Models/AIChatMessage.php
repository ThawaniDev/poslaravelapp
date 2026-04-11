<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIChatMessage extends Model
{
    use HasUuids;

    protected $table = 'ai_chat_messages';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'chat_id',
        'role',
        'content',
        'feature_slug',
        'feature_data',
        'attachments',
        'model_used',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'latency_ms',
    ];

    protected $casts = [
        'feature_data' => 'array',
        'attachments' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_usd' => 'decimal:6',
        'latency_ms' => 'integer',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AIChat::class, 'chat_id');
    }
}
