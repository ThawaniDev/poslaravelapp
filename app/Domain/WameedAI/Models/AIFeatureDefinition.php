<?php

namespace App\Domain\WameedAI\Models;

use App\Domain\WameedAI\Enums\AIFeatureCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIFeatureDefinition extends Model
{
    use HasUuids;

    protected $table = 'ai_feature_definitions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'category',
        'icon',
        'is_enabled',
        'is_premium',
        'default_model',
        'default_max_tokens',
        'cost_per_request_estimate',
        'daily_limit',
        'monthly_limit',
        'requires_subscription_plan',
        'sort_order',
    ];

    protected $casts = [
        'category' => AIFeatureCategory::class,
        'is_enabled' => 'boolean',
        'is_premium' => 'boolean',
        'default_max_tokens' => 'integer',
        'cost_per_request_estimate' => 'decimal:6',
        'daily_limit' => 'integer',
        'monthly_limit' => 'integer',
        'requires_subscription_plan' => 'array',
        'sort_order' => 'integer',
    ];

    public function storeConfigs(): HasMany
    {
        return $this->hasMany(AIStoreFeatureConfig::class, 'ai_feature_definition_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AIUsageLog::class, 'ai_feature_definition_id');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(AIPrompt::class, 'feature_slug', 'slug');
    }
}
