<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeCustomerGroupTemplate extends Model
{
    use HasUuids;

    protected $table = 'business_type_customer_group_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'name',
        'name_ar',
        'description',
        'discount_percentage',
        'credit_limit',
        'payment_terms_days',
        'is_default_group',
        'sort_order',
    ];

    protected $casts = [
        'is_default_group' => 'boolean',
        'discount_percentage' => 'decimal:2',
        'credit_limit' => 'decimal:2',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
