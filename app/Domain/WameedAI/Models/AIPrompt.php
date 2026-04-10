<?php

namespace App\Domain\WameedAI\Models;

use App\Domain\WameedAI\Enums\AIResponseFormat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AIPrompt extends Model
{
    use HasUuids;

    protected $table = 'ai_prompts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'feature_slug',
        'version',
        'system_prompt',
        'user_prompt_template',
        'model',
        'max_tokens',
        'temperature',
        'response_format',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'max_tokens' => 'integer',
        'temperature' => 'decimal:2',
        'response_format' => AIResponseFormat::class,
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForFeature($query, string $featureSlug)
    {
        return $query->where('feature_slug', $featureSlug);
    }
}
