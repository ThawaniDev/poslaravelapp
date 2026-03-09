<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\BusinessCommissionType;
use App\Domain\StaffManagement\Enums\CommissionAppliesTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeCommissionTemplate extends Model
{
    use HasUuids;

    protected $table = 'business_type_commission_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'name',
        'name_ar',
        'commission_type',
        'value',
        'applies_to',
        'tier_thresholds',
        'sort_order',
    ];

    protected $casts = [
        'commission_type' => BusinessCommissionType::class,
        'applies_to' => CommissionAppliesTo::class,
        'tier_thresholds' => 'array',
        'value' => 'decimal:2',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
