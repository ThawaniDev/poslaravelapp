<?php

namespace App\Domain\WameedAI\Models;

use App\Domain\WameedAI\Enums\AIProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AIProviderConfig extends Model
{
    use HasUuids;

    protected $table = 'ai_provider_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'provider',
        'api_key_encrypted',
        'default_model',
        'max_tokens_per_request',
        'is_active',
    ];

    protected $casts = [
        'provider' => AIProvider::class,
        'is_active' => 'boolean',
        'max_tokens_per_request' => 'integer',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];
}
