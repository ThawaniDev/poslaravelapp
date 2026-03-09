<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\BusinessPromotionType;
use App\Domain\Promotion\Enums\PromotionAppliesTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypePromotionTemplate extends Model
{
    use HasUuids;

    protected $table = 'business_type_promotion_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'name',
        'name_ar',
        'description',
        'promotion_type',
        'discount_value',
        'applies_to',
        'time_start',
        'time_end',
        'active_days',
        'minimum_order',
        'sort_order',
    ];

    protected $casts = [
        'promotion_type' => BusinessPromotionType::class,
        'applies_to' => PromotionAppliesTo::class,
        'active_days' => 'array',
        'discount_value' => 'decimal:2',
        'minimum_order' => 'decimal:2',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
