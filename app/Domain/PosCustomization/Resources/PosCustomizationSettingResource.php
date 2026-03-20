<?php

namespace App\Domain\PosCustomization\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosCustomizationSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'store_id'            => $this->store_id,
            'theme'               => $this->theme,
            'primary_color'       => $this->primary_color,
            'secondary_color'     => $this->secondary_color,
            'accent_color'        => $this->accent_color,
            'font_scale'          => (float) $this->font_scale,
            'handedness'          => $this->handedness,
            'grid_columns'        => (int) $this->grid_columns,
            'show_product_images' => (bool) $this->show_product_images,
            'show_price_on_grid'  => (bool) $this->show_price_on_grid,
            'cart_display_mode'   => $this->cart_display_mode,
            'layout_direction'    => $this->layout_direction,
            'sync_version'        => (int) $this->sync_version,
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
