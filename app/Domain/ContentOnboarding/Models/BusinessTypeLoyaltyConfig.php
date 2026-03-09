<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Customer\Enums\LoyaltyProgramType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeLoyaltyConfig extends Model
{
    use HasUuids;

    protected $table = 'business_type_loyalty_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'business_type_id',
        'program_type',
        'earning_rate',
        'redemption_value',
        'min_redemption_points',
        'stamps_card_size',
        'cashback_percentage',
        'points_expiry_days',
        'enable_tiers',
        'tier_definitions',
        'is_active',
    ];

    protected $casts = [
        'program_type' => LoyaltyProgramType::class,
        'tier_definitions' => 'array',
        'enable_tiers' => 'boolean',
        'is_active' => 'boolean',
        'earning_rate' => 'decimal:2',
        'redemption_value' => 'decimal:2',
        'cashback_percentage' => 'decimal:2',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
