<?php

namespace App\Domain\Customer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyConfig extends Model
{
    use HasUuids;

    protected $table = 'loyalty_config';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'points_per_sar',
        'sar_per_point',
        'min_redemption_points',
        'points_expiry_months',
        'excluded_category_ids',
        'double_points_days',
        'is_active',
    ];

    protected $casts = [
        'excluded_category_ids' => 'array',
        'double_points_days' => 'array',
        'is_active' => 'boolean',
        'points_per_sar' => 'decimal:2',
        'sar_per_point' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
