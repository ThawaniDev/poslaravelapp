<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\CfdTheme;
use App\Domain\ContentOnboarding\Models\LabelLayoutTemplate;
use App\Domain\ContentOnboarding\Models\PlatformUiDefault;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use App\Domain\ContentOnboarding\Models\ReceiptLayoutTemplate;
use App\Domain\ContentOnboarding\Models\SignageTemplate;
use App\Domain\ContentOnboarding\Models\Theme;
use Illuminate\Database\Seeder;

class PosLayoutSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlatformDefaults();
        $this->seedThemes();
        $this->seedPosLayouts();
        $this->seedReceiptTemplates();
        $this->seedCfdThemes();
        $this->seedSignageTemplates();
        $this->seedLabelTemplates();
    }

    // ─── Platform UI Defaults ────────────────────────────────

    private function seedPlatformDefaults(): void
    {
        $defaults = [
            'handedness' => 'right',
            'font_size'  => 'medium',
            'theme'      => 'light_classic',
        ];

        foreach ($defaults as $key => $value) {
            PlatformUiDefault::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    // ─── Themes ──────────────────────────────────────────────

    private function seedThemes(): void
    {
        $themes = [
            [
                'name'             => 'Light Classic',
                'slug'             => 'light_classic',
                'primary_color'    => '#1E40AF',
                'secondary_color'  => '#3B82F6',
                'background_color' => '#FFFFFF',
                'text_color'       => '#1F2937',
                'is_system'        => true,
                'is_active'        => true,
            ],
            [
                'name'             => 'Dark Mode',
                'slug'             => 'dark_mode',
                'primary_color'    => '#6366F1',
                'secondary_color'  => '#818CF8',
                'background_color' => '#111827',
                'text_color'       => '#F9FAFB',
                'is_system'        => true,
                'is_active'        => true,
            ],
            [
                'name'             => 'High Contrast',
                'slug'             => 'high_contrast',
                'primary_color'    => '#000000',
                'secondary_color'  => '#FFDD00',
                'background_color' => '#FFFFFF',
                'text_color'       => '#000000',
                'is_system'        => true,
                'is_active'        => true,
            ],
            [
                'name'             => 'Thawani Brand',
                'slug'             => 'thawani_brand',
                'primary_color'    => '#00B67A',
                'secondary_color'  => '#00D68F',
                'background_color' => '#F0FDF4',
                'text_color'       => '#14532D',
                'is_system'        => true,
                'is_active'        => true,
            ],
        ];

        foreach ($themes as $theme) {
            Theme::updateOrCreate(['slug' => $theme['slug']], $theme);
        }
    }

    // ─── POS Layout Templates ────────────────────────────────

    private function seedPosLayouts(): void
    {
        $businessTypes = BusinessType::where('is_active', true)->get();

        foreach ($businessTypes as $bt) {
            $gridConfig = [
                'layout_type'     => 'grid',
                'cart_position'   => 'right',
                'cart_width'      => 35,
                'show_categories' => true,
                'category_style'  => 'tabs',
                'product_display' => 'grid',
                'product_columns' => 4,
                'show_images'     => true,
                'quick_actions'   => ['discount', 'hold', 'refund'],
                'payment_buttons' => ['cash', 'card', 'wallet'],
            ];

            PosLayoutTemplate::updateOrCreate(
                ['layout_key' => $bt->slug . '_standard_grid'],
                [
                    'business_type_id'  => $bt->id,
                    'name'              => 'Standard Grid',
                    'name_ar'           => 'الشبكة القياسية',
                    'description'       => 'Default grid layout for ' . $bt->name,
                    'config'            => $gridConfig,
                    'is_default'        => true,
                    'is_active'         => true,
                    'sort_order'        => 0,
                ],
            );

            $listConfig = [
                'layout_type'     => 'split',
                'cart_position'   => 'right',
                'cart_width'      => 40,
                'show_categories' => true,
                'category_style'  => 'sidebar',
                'product_display' => 'list',
                'product_columns' => 1,
                'show_images'     => false,
                'quick_actions'   => ['discount', 'hold'],
                'payment_buttons' => ['cash', 'card'],
            ];

            PosLayoutTemplate::updateOrCreate(
                ['layout_key' => $bt->slug . '_compact_list'],
                [
                    'business_type_id'  => $bt->id,
                    'name'              => 'Compact List',
                    'name_ar'           => 'القائمة المدمجة',
                    'description'       => 'Compact list layout for ' . $bt->name,
                    'config'            => $listConfig,
                    'is_default'        => false,
                    'is_active'         => true,
                    'sort_order'        => 1,
                ],
            );
        }
    }

    // ─── Receipt Layout Templates ────────────────────────────

    private function seedReceiptTemplates(): void
    {
        $templates = [
            [
                'name'       => 'Standard 80mm',
                'name_ar'    => 'قياسي 80 مم',
                'slug'       => 'standard_80mm',
                'paper_width' => 80,
                'header_config' => [
                    'logo_max_height'     => 60,
                    'store_name_font_size' => 18,
                    'store_name_bold'     => true,
                    'address_font_size'   => 12,
                    'show_vat_number'     => true,
                    'separator_style'     => 'dashes',
                ],
                'body_config' => [
                    'item_font_size'   => 12,
                    'price_alignment'  => 'right',
                    'show_sku'         => false,
                    'show_barcode'     => false,
                    'col_width_name'   => 50,
                    'col_width_qty'    => 15,
                    'col_width_price'  => 35,
                    'row_separator'    => false,
                    'totals_bold'      => true,
                ],
                'footer_config' => [
                    'zatca_qr_size'       => 120,
                    'show_receipt_number' => true,
                    'show_cashier_name'  => true,
                    'custom_footer_en'   => 'Thank you for your purchase!',
                    'custom_footer_ar'   => 'شكراً لتسوقكم!',
                    'thank_you_en'       => 'Visit us again!',
                    'thank_you_ar'       => 'نتشرف بزيارتكم مجدداً!',
                    'show_social_handles' => false,
                ],
                'zatca_qr_position' => 'footer',
                'show_bilingual'    => true,
                'is_active'         => true,
                'sort_order'        => 0,
            ],
            [
                'name'       => 'Compact 58mm',
                'name_ar'    => 'مدمج 58 مم',
                'slug'       => 'compact_58mm',
                'paper_width' => 58,
                'header_config' => [
                    'logo_max_height'     => 40,
                    'store_name_font_size' => 14,
                    'store_name_bold'     => true,
                    'address_font_size'   => 10,
                    'show_vat_number'     => true,
                    'separator_style'     => 'line',
                ],
                'body_config' => [
                    'item_font_size'   => 10,
                    'price_alignment'  => 'right',
                    'show_sku'         => false,
                    'show_barcode'     => false,
                    'col_width_name'   => 50,
                    'col_width_qty'    => 15,
                    'col_width_price'  => 35,
                    'row_separator'    => false,
                    'totals_bold'      => true,
                ],
                'footer_config' => [
                    'zatca_qr_size'       => 80,
                    'show_receipt_number' => true,
                    'show_cashier_name'  => false,
                    'custom_footer_en'   => '',
                    'custom_footer_ar'   => '',
                    'thank_you_en'       => 'Thank you!',
                    'thank_you_ar'       => 'شكراً!',
                    'show_social_handles' => false,
                ],
                'zatca_qr_position' => 'footer',
                'show_bilingual'    => false,
                'is_active'         => true,
                'sort_order'        => 1,
            ],
        ];

        foreach ($templates as $tpl) {
            ReceiptLayoutTemplate::updateOrCreate(['slug' => $tpl['slug']], $tpl);
        }
    }

    // ─── CFD Themes ──────────────────────────────────────────

    private function seedCfdThemes(): void
    {
        $themes = [
            [
                'name'                => 'Clean White',
                'slug'                => 'cfd_clean_white',
                'background_color'    => '#FFFFFF',
                'text_color'          => '#1F2937',
                'accent_color'        => '#2563EB',
                'font_family'         => 'Inter',
                'cart_layout'         => 'list',
                'idle_layout'         => 'slideshow',
                'animation_style'     => 'fade',
                'transition_seconds'  => 5,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'confetti',
                'is_active'           => true,
            ],
            [
                'name'                => 'Dark Elegant',
                'slug'                => 'cfd_dark_elegant',
                'background_color'    => '#0F172A',
                'text_color'          => '#E2E8F0',
                'accent_color'        => '#F59E0B',
                'font_family'         => 'Poppins',
                'cart_layout'         => 'grid',
                'idle_layout'         => 'static_image',
                'animation_style'     => 'slide',
                'transition_seconds'  => 3,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'check',
                'is_active'           => true,
            ],
        ];

        foreach ($themes as $theme) {
            CfdTheme::updateOrCreate(['slug' => $theme['slug']], $theme);
        }
    }

    // ─── Signage Templates ───────────────────────────────────

    private function seedSignageTemplates(): void
    {
        $restaurant = BusinessType::where('slug', 'restaurant')->first();
        $grocery = BusinessType::where('slug', 'grocery')->first();

        $templates = [
            [
                'name'              => 'Menu Board Classic',
                'name_ar'           => 'لوحة القائمة الكلاسيكية',
                'slug'              => 'menu_board_classic',
                'template_type'     => 'menu_board',
                'layout_config'     => [
                    ['region_id' => 'header', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 15, 'default_content' => 'Our Menu'],
                    ['region_id' => 'products', 'type' => 'product_grid', 'x' => 0, 'y' => 15, 'w' => 100, 'h' => 75, 'default_content' => ''],
                    ['region_id' => 'footer', 'type' => 'text', 'x' => 0, 'y' => 90, 'w' => 100, 'h' => 10, 'default_content' => ''],
                ],
                'placeholder_content' => ['header_text' => 'Our Menu', 'footer_text' => 'Enjoy your meal!'],
                'background_color'    => '#1E293B',
                'text_color'          => '#F8FAFC',
                'font_family'         => 'Poppins',
                'transition_style'    => 'fade',
                'is_active'           => true,
                'business_types'      => $restaurant ? [$restaurant->id] : [],
            ],
            [
                'name'              => 'Promo Slideshow',
                'name_ar'           => 'عرض الترويج',
                'slug'              => 'promo_slideshow_default',
                'template_type'     => 'promo_slideshow',
                'layout_config'     => [
                    ['region_id' => 'slide_area', 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 80, 'default_content' => ''],
                    ['region_id' => 'ticker', 'type' => 'text', 'x' => 0, 'y' => 80, 'w' => 100, 'h' => 20, 'default_content' => ''],
                ],
                'placeholder_content' => ['slide_area' => '', 'ticker_text' => 'Special offers today!'],
                'background_color'    => '#FFFFFF',
                'text_color'          => '#111827',
                'font_family'         => 'Inter',
                'transition_style'    => 'slide',
                'is_active'           => true,
                'business_types'      => array_filter([$restaurant?->id, $grocery?->id]),
            ],
        ];

        foreach ($templates as $tplData) {
            $btIds = $tplData['business_types'] ?? [];
            unset($tplData['business_types']);

            $template = SignageTemplate::updateOrCreate(['slug' => $tplData['slug']], $tplData);

            if (! empty($btIds)) {
                $template->businessTypes()->syncWithoutDetaching($btIds);
            }
        }
    }

    // ─── Label Layout Templates ──────────────────────────────

    private function seedLabelTemplates(): void
    {
        $grocery = BusinessType::where('slug', 'grocery')->first();
        $pharmacy = BusinessType::where('slug', 'pharmacy')->first();
        $jewelry = BusinessType::where('slug', 'jewelry')->first();

        $templates = [
            [
                'name'               => 'Standard Barcode',
                'name_ar'            => 'باركود قياسي',
                'slug'               => 'standard_barcode',
                'label_type'         => 'barcode',
                'label_width_mm'     => 50,
                'label_height_mm'    => 25,
                'barcode_type'       => 'CODE128',
                'barcode_position'   => ['x' => 10, 'y' => 30, 'w' => 80, 'h' => 35],
                'show_barcode_number' => true,
                'field_layout'       => [
                    ['field_key' => 'product_name', 'label_en' => 'Product', 'label_ar' => 'المنتج', 'x' => 5, 'y' => 5, 'w' => 90, 'h' => 20, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 70, 'w' => 45, 'h' => 25, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'sku', 'label_en' => 'SKU', 'label_ar' => 'الرمز', 'x' => 55, 'y' => 70, 'w' => 40, 'h' => 25, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'right'],
                ],
                'font_family'        => 'Inter',
                'default_font_size'  => 'medium',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FFFFFF',
                'is_active'          => true,
                'business_types'     => array_filter([$grocery?->id]),
            ],
            [
                'name'               => 'Price Shelf Tag',
                'name_ar'            => 'علامة سعر الرف',
                'slug'               => 'price_shelf_tag',
                'label_type'         => 'shelf',
                'label_width_mm'     => 60,
                'label_height_mm'    => 30,
                'barcode_type'       => 'EAN13',
                'barcode_position'   => ['x' => 60, 'y' => 5, 'w' => 35, 'h' => 40],
                'show_barcode_number' => true,
                'field_layout'       => [
                    ['field_key' => 'product_name', 'label_en' => 'Product', 'label_ar' => 'المنتج', 'x' => 5, 'y' => 5, 'w' => 50, 'h' => 25, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 35, 'w' => 50, 'h' => 35, 'font_size' => 'extra-large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'unit', 'label_en' => 'Unit', 'label_ar' => 'الوحدة', 'x' => 5, 'y' => 75, 'w' => 50, 'h' => 20, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                ],
                'font_family'        => 'Inter',
                'default_font_size'  => 'medium',
                'show_border'        => true,
                'border_style'       => 'dashed',
                'background_color'   => '#FFFFF0',
                'is_active'          => true,
                'business_types'     => array_filter([$grocery?->id]),
            ],
            [
                'name'               => 'Pharmacy Label',
                'name_ar'            => 'ملصق صيدلية',
                'slug'               => 'pharmacy_label',
                'label_type'         => 'pharmacy',
                'label_width_mm'     => 70,
                'label_height_mm'    => 35,
                'barcode_type'       => 'CODE128',
                'barcode_position'   => ['x' => 5, 'y' => 60, 'w' => 90, 'h' => 20],
                'show_barcode_number' => true,
                'field_layout'       => [
                    ['field_key' => 'product_name', 'label_en' => 'Medicine', 'label_ar' => 'الدواء', 'x' => 5, 'y' => 5, 'w' => 90, 'h' => 15, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'drug_schedule', 'label_en' => 'Schedule', 'label_ar' => 'الجدول', 'x' => 5, 'y' => 25, 'w' => 45, 'h' => 15, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                    ['field_key' => 'expiry_date', 'label_en' => 'Exp', 'label_ar' => 'الانتهاء', 'x' => 55, 'y' => 25, 'w' => 40, 'h' => 15, 'font_size' => 'small', 'is_bold' => true, 'alignment' => 'right'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 45, 'w' => 45, 'h' => 15, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'batch_number', 'label_en' => 'Batch', 'label_ar' => 'الدفعة', 'x' => 55, 'y' => 45, 'w' => 40, 'h' => 15, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'right'],
                ],
                'font_family'        => 'Roboto',
                'default_font_size'  => 'small',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FFFFFF',
                'is_active'          => true,
                'business_types'     => array_filter([$pharmacy?->id]),
            ],
            [
                'name'               => 'Jewellery Tag',
                'name_ar'            => 'علامة مجوهرات',
                'slug'               => 'jewellery_tag',
                'label_type'         => 'jewelry',
                'label_width_mm'     => 40,
                'label_height_mm'    => 20,
                'barcode_type'       => 'QR',
                'barcode_position'   => ['x' => 70, 'y' => 5, 'w' => 25, 'h' => 45],
                'show_barcode_number' => false,
                'field_layout'       => [
                    ['field_key' => 'product_name', 'label_en' => 'Item', 'label_ar' => 'الصنف', 'x' => 5, 'y' => 5, 'w' => 60, 'h' => 20, 'font_size' => 'small', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'karat', 'label_en' => 'Karat', 'label_ar' => 'العيار', 'x' => 5, 'y' => 30, 'w' => 30, 'h' => 20, 'font_size' => 'small', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'weight', 'label_en' => 'Weight', 'label_ar' => 'الوزن', 'x' => 35, 'y' => 30, 'w' => 30, 'h' => 20, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 55, 'w' => 60, 'h' => 20, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'making_charge', 'label_en' => 'Making', 'label_ar' => 'المصنعية', 'x' => 5, 'y' => 80, 'w' => 60, 'h' => 15, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                ],
                'font_family'        => 'Playfair Display',
                'default_font_size'  => 'small',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FFFDF0',
                'is_active'          => true,
                'business_types'     => array_filter([$jewelry?->id]),
            ],
        ];

        foreach ($templates as $tplData) {
            $btIds = $tplData['business_types'] ?? [];
            unset($tplData['business_types']);

            $template = LabelLayoutTemplate::updateOrCreate(['slug' => $tplData['slug']], $tplData);

            if (! empty($btIds)) {
                $template->businessTypes()->syncWithoutDetaching($btIds);
            }
        }
    }
}
