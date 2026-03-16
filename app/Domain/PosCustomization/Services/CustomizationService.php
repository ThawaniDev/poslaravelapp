<?php

namespace App\Domain\PosCustomization\Services;

use App\Domain\PosCustomization\Models\PosCustomizationSetting;
use App\Domain\PosCustomization\Models\ReceiptTemplate;
use App\Domain\PosCustomization\Models\QuickAccessConfig;

class CustomizationService
{
    // ─── POS Customization Settings ──────────────────────

    public function getSettings(string $storeId): array
    {
        $setting = PosCustomizationSetting::where('store_id', $storeId)->first();

        if (! $setting) {
            return $this->defaultSettings($storeId);
        }

        return $setting->toArray();
    }

    public function updateSettings(string $storeId, array $data): array
    {
        $setting = PosCustomizationSetting::updateOrCreate(
            ['store_id' => $storeId],
            array_merge($data, ['sync_version' => now()->timestamp]),
        );

        return $setting->toArray();
    }

    public function resetSettings(string $storeId): array
    {
        PosCustomizationSetting::where('store_id', $storeId)->delete();

        return $this->defaultSettings($storeId);
    }

    // ─── Receipt Template ────────────────────────────────

    public function getReceiptTemplate(string $storeId): array
    {
        $template = ReceiptTemplate::where('store_id', $storeId)->first();

        if (! $template) {
            return $this->defaultReceiptTemplate($storeId);
        }

        return $template->toArray();
    }

    public function updateReceiptTemplate(string $storeId, array $data): array
    {
        $template = ReceiptTemplate::updateOrCreate(
            ['store_id' => $storeId],
            array_merge($data, ['sync_version' => now()->timestamp]),
        );

        return $template->toArray();
    }

    public function resetReceiptTemplate(string $storeId): array
    {
        ReceiptTemplate::where('store_id', $storeId)->delete();

        return $this->defaultReceiptTemplate($storeId);
    }

    // ─── Quick Access Config ─────────────────────────────

    public function getQuickAccess(string $storeId): array
    {
        $config = QuickAccessConfig::where('store_id', $storeId)->first();

        if (! $config) {
            return $this->defaultQuickAccess($storeId);
        }

        return $config->toArray();
    }

    public function updateQuickAccess(string $storeId, array $data): array
    {
        $config = QuickAccessConfig::updateOrCreate(
            ['store_id' => $storeId],
            array_merge($data, ['sync_version' => now()->timestamp]),
        );

        return $config->toArray();
    }

    public function resetQuickAccess(string $storeId): array
    {
        QuickAccessConfig::where('store_id', $storeId)->delete();

        return $this->defaultQuickAccess($storeId);
    }

    // ─── Full Export ─────────────────────────────────────

    public function exportAll(string $storeId): array
    {
        return [
            'settings' => $this->getSettings($storeId),
            'receipt_template' => $this->getReceiptTemplate($storeId),
            'quick_access' => $this->getQuickAccess($storeId),
        ];
    }

    // ─── Defaults ────────────────────────────────────────

    private function defaultSettings(string $storeId): array
    {
        return [
            'store_id' => $storeId,
            'theme' => 'light',
            'primary_color' => '#FD8209',
            'secondary_color' => '#1A1A2E',
            'accent_color' => '#16213E',
            'font_scale' => 1.00,
            'handedness' => 'right',
            'grid_columns' => 4,
            'show_product_images' => true,
            'show_price_on_grid' => true,
            'cart_display_mode' => 'detailed',
            'layout_direction' => 'auto',
            'sync_version' => 0,
        ];
    }

    private function defaultReceiptTemplate(string $storeId): array
    {
        return [
            'store_id' => $storeId,
            'logo_url' => null,
            'header_line_1' => null,
            'header_line_2' => null,
            'footer_text' => null,
            'show_vat_number' => true,
            'show_loyalty_points' => false,
            'show_barcode' => true,
            'paper_width_mm' => 80,
            'sync_version' => 0,
        ];
    }

    private function defaultQuickAccess(string $storeId): array
    {
        return [
            'store_id' => $storeId,
            'grid_rows' => 2,
            'grid_cols' => 4,
            'buttons_json' => [],
            'sync_version' => 0,
        ];
    }
}
