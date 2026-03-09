<?php

namespace App\Domain\DeliveryIntegration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformDeliveryIntegration extends Model
{
    use HasUuids;

    protected $table = 'platform_delivery_integrations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'platform_slug',
        'display_name',
        'display_name_ar',
        'api_base_url',
        'client_id',
        'client_secret_encrypted',
        'webhook_secret_encrypted',
        'default_commission_percent',
        'is_active',
        'supported_countries',
        'logo_url',
    ];

    protected $casts = [
        'supported_countries' => 'array',
        'is_active' => 'boolean',
        'default_commission_percent' => 'decimal:2',
    ];

    public function storeDeliveryPlatformEnrollments(): HasMany
    {
        return $this->hasMany(StoreDeliveryPlatformEnrollment::class, 'platform_slug');
    }
}
