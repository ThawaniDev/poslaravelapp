<?php

namespace App\Domain\SystemConfig\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ABTestEvent extends Model
{
    use HasUuids;

    protected $table = 'ab_test_events';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ab_test_id',
        'variant_id',
        'event_type',
        'store_id',
        'user_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(ABTest::class, 'ab_test_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ABTestVariant::class, 'variant_id');
    }
}
