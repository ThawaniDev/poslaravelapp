<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AIChat extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'ai_chats';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'store_id',
        'user_id',
        'title',
        'llm_model_id',
        'message_count',
        'total_tokens',
        'total_cost_usd',
        'last_message_at',
    ];

    protected $casts = [
        'message_count' => 'integer',
        'total_tokens' => 'integer',
        'total_cost_usd' => 'decimal:6',
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AIChatMessage::class, 'chat_id');
    }

    public function llmModel(): BelongsTo
    {
        return $this->belongsTo(AILlmModel::class, 'llm_model_id');
    }
}
