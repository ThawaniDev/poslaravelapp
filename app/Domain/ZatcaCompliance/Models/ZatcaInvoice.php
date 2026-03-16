<?php

namespace App\Domain\ZatcaCompliance\Models;

use App\Domain\ZatcaCompliance\Enums\ZatcaInvoiceType;
use App\Domain\ZatcaCompliance\Enums\ZatcaSubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaInvoice extends Model
{
    use HasUuids;

    protected $table = 'zatca_invoices';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'order_id',
        'invoice_number',
        'invoice_type',
        'invoice_xml',
        'invoice_hash',
        'previous_invoice_hash',
        'digital_signature',
        'qr_code_data',
        'total_amount',
        'vat_amount',
        'submission_status',
        'zatca_response_code',
        'zatca_response_message',
        'submitted_at',
        'created_at',
    ];

    protected $casts = [
        'invoice_type' => ZatcaInvoiceType::class,
        'submission_status' => ZatcaSubmissionStatus::class,
        'total_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
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
