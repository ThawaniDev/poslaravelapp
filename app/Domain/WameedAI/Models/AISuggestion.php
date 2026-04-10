<?php

namespace App\Domain\WameedAI\Models;

use App\Domain\WameedAI\Enums\AISuggestionPriority;
use App\Domain\WameedAI\Enums\AISuggestionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AISuggestion extends Model
{
    use HasUuids;

    protected $table = 'ai_suggestions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'feature_slug',
        'suggestion_type',
        'title',
        'title_ar',
        'content_json',
        'priority',
        'status',
        'accepted_at',
        'dismissed_at',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'priority' => AISuggestionPriority::class,
        'status' => AISuggestionStatus::class,
        'content_json' => 'array',
        'accepted_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }
}
