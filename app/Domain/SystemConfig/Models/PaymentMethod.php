<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\Payment\Enums\PaymentMethodCategory;
use App\Domain\Payment\Enums\PaymentMethodKey;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasUuids;

    protected $table = 'payment_methods';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'method_key',
        'name',
        'name_ar',
        'icon',
        'category',
        'requires_terminal',
        'requires_customer_profile',
        'provider_config_schema',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'method_key' => PaymentMethodKey::class,
        'category' => PaymentMethodCategory::class,
        'provider_config_schema' => 'array',
        'requires_terminal' => 'boolean',
        'requires_customer_profile' => 'boolean',
        'is_active' => 'boolean',
    ];

}
