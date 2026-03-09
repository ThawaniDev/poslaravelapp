<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\AccountingIntegration\Enums\AccountingProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AccountingIntegrationConfig extends Model
{
    use HasUuids;

    protected $table = 'accounting_integration_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'provider_name',
        'client_id_encrypted',
        'client_secret_encrypted',
        'redirect_url',
        'is_active',
    ];

    protected $casts = [
        'provider_name' => AccountingProvider::class,
        'is_active' => 'boolean',
    ];

}
