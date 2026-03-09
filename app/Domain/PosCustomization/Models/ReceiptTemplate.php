<?php

namespace App\Domain\PosCustomization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptTemplate extends Model
{
    use HasUuids;

    protected $table = 'receipt_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'logo_url',
        'header_line_1',
        'header_line_2',
        'footer_text',
        'show_vat_number',
        'show_loyalty_points',
        'show_barcode',
        'paper_width_mm',
        'sync_version',
    ];

    protected $casts = [
        'show_vat_number' => 'boolean',
        'show_loyalty_points' => 'boolean',
        'show_barcode' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
