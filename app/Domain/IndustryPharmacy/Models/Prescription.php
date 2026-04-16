<?php

namespace App\Domain\IndustryPharmacy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    use HasUuids;

    protected $table = 'prescriptions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'order_id',
        'prescription_number',
        'patient_name',
        'patient_id',
        'doctor_name',
        'doctor_license',
        'insurance_provider',
        'insurance_claim_amount',
        'notes',
    ];

    protected $casts = [
        'insurance_claim_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
