<?php

namespace App\Domain\WameedAI\Models;

use App\Domain\WameedAI\Enums\AIProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AILlmModel extends Model
{
    use HasUuids;

    protected $table = 'ai_llm_models';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'provider',
        'model_id',
        'display_name',
        'description',
        'api_key_encrypted',
        'supports_vision',
        'supports_json_mode',
        'max_context_tokens',
        'max_output_tokens',
        'input_price_per_1m',
        'output_price_per_1m',
        'is_enabled',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'provider' => AIProvider::class,
        'supports_vision' => 'boolean',
        'supports_json_mode' => 'boolean',
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'max_context_tokens' => 'integer',
        'max_output_tokens' => 'integer',
        'input_price_per_1m' => 'decimal:4',
        'output_price_per_1m' => 'decimal:4',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeWithVision($query)
    {
        return $query->where('supports_vision', true);
    }
}
