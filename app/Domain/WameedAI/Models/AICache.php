<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AICache extends Model
{
    use HasUuids;

    protected $table = 'ai_cache';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'cache_key',
        'feature_slug',
        'store_id',
        'response_text',
        'tokens_used',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
