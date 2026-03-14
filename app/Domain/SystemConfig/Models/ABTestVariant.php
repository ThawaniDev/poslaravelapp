<?php

namespace App\Domain\SystemConfig\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ABTestVariant extends Model
{
    use HasUuids;

    protected $table = 'ab_test_variants';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ab_test_id',
        'variant_key',
        'variant_label',
        'weight',
        'is_control',
        'metadata',
    ];

    protected $casts = [
        'weight' => 'integer',
        'is_control' => 'boolean',
        'metadata' => 'array',
    ];

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(ABTest::class, 'ab_test_id');
    }
}
