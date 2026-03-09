<?php

namespace App\Domain\DeliveryIntegration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDeliveryPlatformEnrollment extends Model
{
    use HasUuids;

    protected $table = 'store_delivery_platform_enrollments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'platform_slug',
        'merchant_id_on_platform',
        'is_enabled',
        'auto_accept',
        'commission_override',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'auto_accept' => 'boolean',
        'commission_override' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function platformSlug(): BelongsTo
    {
        return $this->belongsTo(PlatformDeliveryIntegration::class, 'platform_slug');
    }
}
