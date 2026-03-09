<?php

namespace App\Domain\PosCustomization\Models;

use App\Domain\ContentOnboarding\Enums\CartDisplayMode;
use App\Domain\ContentOnboarding\Enums\Handedness;
use App\Domain\ContentOnboarding\Enums\LayoutDirection;
use App\Domain\PosCustomization\Enums\PosTheme;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosCustomizationSetting extends Model
{
    use HasUuids;

    protected $table = 'pos_customization_settings';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'theme',
        'primary_color',
        'secondary_color',
        'accent_color',
        'font_scale',
        'handedness',
        'grid_columns',
        'show_product_images',
        'show_price_on_grid',
        'cart_display_mode',
        'layout_direction',
        'sync_version',
    ];

    protected $casts = [
        'theme' => PosTheme::class,
        'handedness' => Handedness::class,
        'cart_display_mode' => CartDisplayMode::class,
        'layout_direction' => LayoutDirection::class,
        'show_product_images' => 'boolean',
        'show_price_on_grid' => 'boolean',
        'font_scale' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
