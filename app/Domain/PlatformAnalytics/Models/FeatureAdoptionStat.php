<?php

namespace App\Domain\PlatformAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FeatureAdoptionStat extends Model
{
    use HasUuids;

    protected $table = 'feature_adoption_stats';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d';

    protected $fillable = [
        'feature_key',
        'date',
        'stores_using_count',
        'total_events',
    ];

    protected $casts = [
        'date' => 'date',
    ];

}
