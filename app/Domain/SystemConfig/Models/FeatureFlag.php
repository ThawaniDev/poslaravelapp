<?php

namespace App\Domain\SystemConfig\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use HasUuids;

    protected $table = 'feature_flags';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'flag_key',
        'is_enabled',
        'rollout_percentage',
        'target_plan_ids',
        'target_store_ids',
        'description',
    ];

    protected $casts = [
        'target_plan_ids' => 'array',
        'target_store_ids' => 'array',
        'is_enabled' => 'boolean',
    ];

}
