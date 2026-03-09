<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeShiftTemplate extends Model
{
    use HasUuids;

    protected $table = 'business_type_shift_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'name',
        'name_ar',
        'start_time',
        'end_time',
        'days_of_week',
        'break_duration_minutes',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'is_default' => 'boolean',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
