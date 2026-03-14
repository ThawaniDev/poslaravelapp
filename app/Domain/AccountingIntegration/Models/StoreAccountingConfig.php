<?php

namespace App\Domain\AccountingIntegration\Models;

use App\Domain\AccountingIntegration\Enums\AccountingProvider;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreAccountingConfig extends Model
{
    use HasUuids;

    protected $table = 'store_accounting_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'provider',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'token_expires_at',
        'realm_id',
        'tenant_id',
        'company_name',
        'connected_at',
        'last_sync_at',
    ];

    protected $casts = [
        'provider' => AccountingProvider::class,
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
