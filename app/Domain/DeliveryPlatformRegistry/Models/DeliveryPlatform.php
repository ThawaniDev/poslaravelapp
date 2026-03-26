<?php

namespace App\Domain\DeliveryPlatformRegistry\Models;

use App\Domain\DeliveryPlatformRegistry\Enums\DeliveryAuthMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryPlatform extends Model
{
    use HasUuids;

    protected $table = 'delivery_platforms';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'auth_method',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'auth_method' => DeliveryAuthMethod::class,
        'is_active' => 'boolean',
    ];

    public function deliveryPlatformFields(): HasMany
    {
        return $this->hasMany(DeliveryPlatformField::class);
    }

    public function fields(): HasMany
    {
        return $this->deliveryPlatformFields();
    }

    public function deliveryPlatformEndpoints(): HasMany
    {
        return $this->hasMany(DeliveryPlatformEndpoint::class);
    }

    public function endpoints(): HasMany
    {
        return $this->deliveryPlatformEndpoints();
    }

    public function deliveryPlatformWebhookTemplates(): HasMany
    {
        return $this->hasMany(DeliveryPlatformWebhookTemplate::class);
    }
    public function storeDeliveryPlatforms(): HasMany
    {
        return $this->hasMany(StoreDeliveryPlatform::class);
    }
}
