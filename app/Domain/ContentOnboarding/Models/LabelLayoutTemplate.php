<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Catalog\Enums\BarcodeType;
use App\Domain\ContentOnboarding\Enums\BorderStyle;
use App\Domain\ContentOnboarding\Enums\FontSize;
use App\Domain\ContentOnboarding\Enums\LabelType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelLayoutTemplate extends Model
{
    use HasUuids;

    protected $table = 'label_layout_templates';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'label_type',
        'label_width_mm',
        'label_height_mm',
        'barcode_type',
        'barcode_position',
        'show_barcode_number',
        'field_layout',
        'font_family',
        'default_font_size',
        'show_border',
        'border_style',
        'background_color',
        'preview_image_url',
        'is_active',
    ];

    protected $casts = [
        'label_type' => LabelType::class,
        'barcode_type' => BarcodeType::class,
        'default_font_size' => FontSize::class,
        'border_style' => BorderStyle::class,
        'barcode_position' => 'array',
        'field_layout' => 'array',
        'show_barcode_number' => 'boolean',
        'show_border' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function labelTemplateBusinessTypes(): HasMany
    {
        return $this->hasMany(LabelTemplateBusinessType::class);
    }
    public function labelTemplatePackageVisibility(): HasMany
    {
        return $this->hasMany(LabelTemplatePackageVisibility::class);
    }
}
