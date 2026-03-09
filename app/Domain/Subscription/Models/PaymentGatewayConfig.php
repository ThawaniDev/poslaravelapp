<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Subscription\Enums\GatewayEnvironment;
use App\Domain\Subscription\Enums\GatewayName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    use HasUuids;

    protected $table = 'payment_gateway_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'gateway_name',
        'credentials_encrypted',
        'webhook_url',
        'environment',
        'is_active',
    ];

    protected $casts = [
        'gateway_name' => GatewayName::class,
        'environment' => GatewayEnvironment::class,
        'credentials_encrypted' => 'array',
        'is_active' => 'boolean',
    ];

}
