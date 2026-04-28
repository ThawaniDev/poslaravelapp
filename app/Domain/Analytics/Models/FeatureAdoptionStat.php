<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FeatureAdoptionStat extends Model
{
    use HasUuids;

    protected $table = 'feature_adoption_stats';
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'feature_key',
        'date',
        'stores_using_count',
        'total_events',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : $value;
    }
}
