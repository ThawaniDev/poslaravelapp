<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeGiftRegistryType extends Model
{
    use HasUuids;

    protected $table = 'business_type_gift_registry_types';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'name',
        'name_ar',
        'description',
        'icon',
        'default_expiry_days',
        'allow_public_sharing',
        'allow_partial_fulfilment',
        'require_minimum_items',
        'minimum_items_count',
        'sort_order',
    ];

    protected $casts = [
        'allow_public_sharing' => 'boolean',
        'allow_partial_fulfilment' => 'boolean',
        'require_minimum_items' => 'boolean',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
