<?php

namespace App\Domain\AccountingIntegration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    use HasUuids;

    protected $table = 'account_mappings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'pos_account_key',
        'provider_account_id',
        'provider_account_name',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
