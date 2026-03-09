<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\FontSize;
use App\Domain\ZatcaCompliance\Enums\ZatcaQrPosition;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeReceiptTemplate extends Model
{
    use HasUuids;

    protected $table = 'business_type_receipt_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'paper_width',
        'header_sections',
        'body_sections',
        'footer_sections',
        'zatca_qr_position',
        'show_bilingual',
        'font_size',
        'custom_footer_text',
        'custom_footer_text_ar',
    ];

    protected $casts = [
        'zatca_qr_position' => ZatcaQrPosition::class,
        'font_size' => FontSize::class,
        'header_sections' => 'array',
        'body_sections' => 'array',
        'footer_sections' => 'array',
        'show_bilingual' => 'boolean',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
