<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIStoreFeatureConfig extends Model
{
    use HasUuids;

    protected $table = 'ai_store_feature_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'store_id',
        'ai_feature_definition_id',
        'is_enabled',
        'daily_limit',
        'monthly_limit',
        'custom_prompt_override',
        'settings_json',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'daily_limit' => 'integer',
        'monthly_limit' => 'integer',
        'settings_json' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function featureDefinition(): BelongsTo
    {
        return $this->belongsTo(AIFeatureDefinition::class, 'ai_feature_definition_id');
    }
}
