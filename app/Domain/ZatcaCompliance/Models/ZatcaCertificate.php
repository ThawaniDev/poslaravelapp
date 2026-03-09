<?php

namespace App\Domain\ZatcaCompliance\Models;

use App\Domain\ZatcaCompliance\Enums\ZatcaCertificateStatus;
use App\Domain\ZatcaCompliance\Enums\ZatcaCertificateType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaCertificate extends Model
{
    use HasUuids;

    protected $table = 'zatca_certificates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'certificate_type',
        'certificate_pem',
        'ccsid',
        'issued_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'certificate_type' => ZatcaCertificateType::class,
        'status' => ZatcaCertificateStatus::class,
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
