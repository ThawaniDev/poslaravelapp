<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeIndustryConfig extends Model
{
    use HasUuids;

    protected $table = 'business_type_industry_configs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'active_modules',
        'default_settings',
        'required_product_fields',
    ];

    protected $casts = [
        'active_modules' => 'array',
        'default_settings' => 'array',
        'required_product_fields' => 'array',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
