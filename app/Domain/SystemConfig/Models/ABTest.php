<?php

namespace App\Domain\SystemConfig\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ABTest extends Model
{
    use HasUuids;

    protected $table = 'ab_tests';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'feature_flag_id',
        'status',
        'start_date',
        'end_date',
        'metric_key',
        'traffic_percentage',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'traffic_percentage' => 'integer',
    ];

    public function featureFlag(): BelongsTo
    {
        return $this->belongsTo(FeatureFlag::class, 'feature_flag_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ABTestVariant::class, 'ab_test_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ABTestEvent::class, 'ab_test_id');
    }
}
