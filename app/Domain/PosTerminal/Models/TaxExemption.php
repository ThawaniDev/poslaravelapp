<?php

namespace App\Domain\PosTerminal\Models;

use App\Domain\SystemConfig\Enums\TaxExemptionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxExemption extends Model
{
    use HasUuids;

    protected $table = 'tax_exemptions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'customer_id',
        'exemption_type',
        'customer_tax_id',
        'certificate_number',
        'notes',
    ];

    protected $casts = [
        'exemption_type' => TaxExemptionType::class,
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
