<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Customer\Enums\WasteReasonCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeWasteReasonTemplate extends Model
{
    use HasUuids;

    protected $table = 'business_type_waste_reason_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'reason_code',
        'name',
        'name_ar',
        'category',
        'description',
        'requires_approval',
        'affects_cost_reporting',
        'sort_order',
    ];

    protected $casts = [
        'category' => WasteReasonCategory::class,
        'requires_approval' => 'boolean',
        'affects_cost_reporting' => 'boolean',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
