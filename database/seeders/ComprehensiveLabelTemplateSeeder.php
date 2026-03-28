<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\LabelLayoutTemplate;
use Illuminate\Database\Seeder;

class ComprehensiveLabelTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $bt = fn (string $slug) => BusinessType::where('slug', $slug)->first()?->id;

        $templates = [
            // ── 1. Standard Barcode (existing, enhanced) ──
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
                'business_types'     => ['grocery', 'retail'],
            ],
            // ── 2. Price Shelf Tag (existing, enhanced) ──
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
                'business_types'     => ['grocery', 'retail'],
            ],
            // ── 3. Pharmacy Label (existing, enhanced) ──
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
                    ['field_key' => 'drug_schedule', 'label_en' => 'Schedule', 'label_ar' => 'الجدول', 'x' => 5, 'y' => 22, 'w' => 45, 'h' => 12, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                    ['field_key' => 'expiry_date', 'label_en' => 'Exp', 'label_ar' => 'الانتهاء', 'x' => 55, 'y' => 22, 'w' => 40, 'h' => 12, 'font_size' => 'small', 'is_bold' => true, 'alignment' => 'right'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 38, 'w' => 45, 'h' => 15, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'batch_number', 'label_en' => 'Batch', 'label_ar' => 'الدفعة', 'x' => 55, 'y' => 38, 'w' => 40, 'h' => 15, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'right'],
                ],
                'font_family'        => 'Roboto',
                'default_font_size'  => 'small',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FFFFFF',
                'is_active'          => true,
                'business_types'     => ['pharmacy'],
            ],
            // ── 4. Jewellery Tag (existing, enhanced) ──
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
                'business_types'     => ['jewelry'],
            ],
            // ── 5. Food Expiry Label – grocery/bakery expiry tracking ──
            [
                'name'               => 'Food Expiry Label',
                'name_ar'            => 'ملصق انتهاء الصلاحية',
                'slug'               => 'food_expiry_label',
                'label_type'         => 'barcode',
                'label_width_mm'     => 50,
                'label_height_mm'    => 30,
                'barcode_type'       => 'CODE128',
                'barcode_position'   => ['x' => 5, 'y' => 65, 'w' => 90, 'h' => 20],
                'show_barcode_number' => true,
                'field_layout'       => [
                    ['field_key' => 'product_name', 'label_en' => 'Product', 'label_ar' => 'المنتج', 'x' => 5, 'y' => 3, 'w' => 90, 'h' => 15, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'manufacture_date', 'label_en' => 'Mfg', 'label_ar' => 'الإنتاج', 'x' => 5, 'y' => 20, 'w' => 45, 'h' => 15, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                    ['field_key' => 'expiry_date', 'label_en' => 'Exp', 'label_ar' => 'الانتهاء', 'x' => 50, 'y' => 20, 'w' => 45, 'h' => 15, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'right'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 38, 'w' => 45, 'h' => 22, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'weight', 'label_en' => 'Weight', 'label_ar' => 'الوزن', 'x' => 50, 'y' => 38, 'w' => 45, 'h' => 22, 'font_size' => 'medium', 'is_bold' => false, 'alignment' => 'right'],
                ],
                'font_family'        => 'Inter',
                'default_font_size'  => 'small',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FEF9E7',
                'is_active'          => true,
                'business_types'     => ['grocery', 'bakery'],
            ],
            // ── 6. Electronics IMEI Label – IMEI/serial tracking ──
            [
                'name'               => 'Electronics IMEI Label',
                'name_ar'            => 'ملصق IMEI إلكترونيات',
                'slug'               => 'electronics_imei_label',
                'label_type'         => 'barcode',
                'label_width_mm'     => 60,
                'label_height_mm'    => 30,
                'barcode_type'       => 'CODE128',
                'barcode_position'   => ['x' => 5, 'y' => 55, 'w' => 90, 'h' => 25],
                'show_barcode_number' => true,
                'field_layout'       => [
                    ['field_key' => 'product_name', 'label_en' => 'Device', 'label_ar' => 'الجهاز', 'x' => 5, 'y' => 3, 'w' => 60, 'h' => 15, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'sku', 'label_en' => 'Model', 'label_ar' => 'الموديل', 'x' => 65, 'y' => 3, 'w' => 30, 'h' => 15, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'right'],
                    ['field_key' => 'custom_text', 'label_en' => 'IMEI', 'label_ar' => 'IMEI', 'x' => 5, 'y' => 20, 'w' => 90, 'h' => 12, 'font_size' => 'small', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 35, 'w' => 45, 'h' => 18, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'origin_country', 'label_en' => 'Origin', 'label_ar' => 'المنشأ', 'x' => 55, 'y' => 35, 'w' => 40, 'h' => 18, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'right'],
                ],
                'font_family'        => 'Roboto Mono',
                'default_font_size'  => 'small',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FFFFFF',
                'is_active'          => true,
                'business_types'     => ['mobile_shop'],
            ],
            // ── 7. Clothing Size Tag – fashion/apparel ──
            [
                'name'               => 'Clothing Size Tag',
                'name_ar'            => 'بطاقة مقاس الملابس',
                'slug'               => 'clothing_size_tag',
                'label_type'         => 'price',
                'label_width_mm'     => 40,
                'label_height_mm'    => 60,
                'barcode_type'       => 'CODE128',
                'barcode_position'   => ['x' => 10, 'y' => 70, 'w' => 80, 'h' => 20],
                'show_barcode_number' => true,
                'field_layout'       => [
                    ['field_key' => 'store_name', 'label_en' => 'Brand', 'label_ar' => 'العلامة', 'x' => 5, 'y' => 3, 'w' => 90, 'h' => 10, 'font_size' => 'small', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'product_name', 'label_en' => 'Item', 'label_ar' => 'الصنف', 'x' => 5, 'y' => 15, 'w' => 90, 'h' => 12, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'custom_text', 'label_en' => 'Size', 'label_ar' => 'المقاس', 'x' => 20, 'y' => 30, 'w' => 60, 'h' => 18, 'font_size' => 'extra-large', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 50, 'w' => 90, 'h' => 15, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'origin_country', 'label_en' => 'Made in', 'label_ar' => 'صنع في', 'x' => 5, 'y' => 92, 'w' => 90, 'h' => 8, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'center'],
                ],
                'font_family'        => 'Inter',
                'default_font_size'  => 'small',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FFFFFF',
                'is_active'          => true,
                'business_types'     => ['retail'],
            ],
            // ── 8. Weight Scale Label – auto-weigh produce label ──
            [
                'name'               => 'Weight Scale Label',
                'name_ar'            => 'ملصق ميزان الوزن',
                'slug'               => 'weight_scale_label',
                'label_type'         => 'barcode',
                'label_width_mm'     => 58,
                'label_height_mm'    => 40,
                'barcode_type'       => 'EAN13',
                'barcode_position'   => ['x' => 5, 'y' => 65, 'w' => 90, 'h' => 22],
                'show_barcode_number' => true,
                'field_layout'       => [
                    ['field_key' => 'product_name', 'label_en' => 'Product', 'label_ar' => 'المنتج', 'x' => 5, 'y' => 3, 'w' => 90, 'h' => 14, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'weight', 'label_en' => 'Net Weight', 'label_ar' => 'الوزن الصافي', 'x' => 5, 'y' => 19, 'w' => 50, 'h' => 20, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'unit', 'label_en' => 'kg', 'label_ar' => 'كجم', 'x' => 55, 'y' => 19, 'w' => 15, 'h' => 20, 'font_size' => 'medium', 'is_bold' => false, 'alignment' => 'left'],
                    ['field_key' => 'price', 'label_en' => 'Price/kg', 'label_ar' => 'السعر/كجم', 'x' => 5, 'y' => 42, 'w' => 45, 'h' => 18, 'font_size' => 'medium', 'is_bold' => false, 'alignment' => 'left'],
                    ['field_key' => 'custom_text', 'label_en' => 'Total', 'label_ar' => 'الإجمالي', 'x' => 50, 'y' => 42, 'w' => 45, 'h' => 18, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'right'],
                ],
                'font_family'        => 'Roboto',
                'default_font_size'  => 'medium',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FFFFFF',
                'is_active'          => true,
                'business_types'     => ['grocery'],
            ],
            // ── 9. Organic Certification Label – organic/bio products ──
            [
                'name'               => 'Organic Certification Label',
                'name_ar'            => 'ملصق شهادة عضوي',
                'slug'               => 'organic_cert_label',
                'label_type'         => 'barcode',
                'label_width_mm'     => 50,
                'label_height_mm'    => 30,
                'barcode_type'       => 'QR',
                'barcode_position'   => ['x' => 72, 'y' => 5, 'w' => 23, 'h' => 40],
                'show_barcode_number' => false,
                'field_layout'       => [
                    ['field_key' => 'product_name', 'label_en' => 'Product', 'label_ar' => 'المنتج', 'x' => 5, 'y' => 3, 'w' => 62, 'h' => 15, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'custom_text', 'label_en' => '🌿 ORGANIC', 'label_ar' => '🌿 عضوي', 'x' => 5, 'y' => 20, 'w' => 62, 'h' => 14, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'origin_country', 'label_en' => 'Origin', 'label_ar' => 'المنشأ', 'x' => 5, 'y' => 36, 'w' => 62, 'h' => 12, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 52, 'w' => 40, 'h' => 18, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'expiry_date', 'label_en' => 'Best Before', 'label_ar' => 'يفضل قبل', 'x' => 5, 'y' => 73, 'w' => 62, 'h' => 12, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                    ['field_key' => 'batch_number', 'label_en' => 'Lot', 'label_ar' => 'الدفعة', 'x' => 5, 'y' => 87, 'w' => 62, 'h' => 10, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'left'],
                ],
                'font_family'        => 'Nunito',
                'default_font_size'  => 'small',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#F0FDF4',
                'is_active'          => true,
                'business_types'     => ['grocery'],
            ],
            // ── 10. Gift Tag – elegant gift/flower tag ──
            [
                'name'               => 'Gift Tag',
                'name_ar'            => 'بطاقة هدية',
                'slug'               => 'gift_tag',
                'label_type'         => 'price',
                'label_width_mm'     => 50,
                'label_height_mm'    => 35,
                'barcode_type'       => 'QR',
                'barcode_position'   => ['x' => 72, 'y' => 55, 'w' => 23, 'h' => 35],
                'show_barcode_number' => false,
                'field_layout'       => [
                    ['field_key' => 'store_name', 'label_en' => 'From', 'label_ar' => 'من', 'x' => 5, 'y' => 3, 'w' => 90, 'h' => 12, 'font_size' => 'small', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'product_name', 'label_en' => 'Item', 'label_ar' => 'الصنف', 'x' => 5, 'y' => 18, 'w' => 90, 'h' => 15, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'center'],
                    ['field_key' => 'custom_text', 'label_en' => 'Message', 'label_ar' => 'رسالة', 'x' => 5, 'y' => 36, 'w' => 90, 'h' => 15, 'font_size' => 'small', 'is_bold' => false, 'alignment' => 'center'],
                    ['field_key' => 'price', 'label_en' => 'Price', 'label_ar' => 'السعر', 'x' => 5, 'y' => 55, 'w' => 60, 'h' => 18, 'font_size' => 'large', 'is_bold' => true, 'alignment' => 'left'],
                    ['field_key' => 'custom_text', 'label_en' => '♥', 'label_ar' => '♥', 'x' => 5, 'y' => 78, 'w' => 60, 'h' => 18, 'font_size' => 'large', 'is_bold' => false, 'alignment' => 'center'],
                ],
                'font_family'        => 'Playfair Display',
                'default_font_size'  => 'small',
                'show_border'        => true,
                'border_style'       => 'solid',
                'background_color'   => '#FFF1F2',
                'is_active'          => true,
                'business_types'     => ['flower_shop', 'jewelry', 'retail'],
            ],
        ];

        foreach ($templates as $tplData) {
            $btSlugs = $tplData['business_types'] ?? [];
            unset($tplData['business_types']);

            $btIds = array_filter(array_map(fn ($s) => $bt($s), $btSlugs));

            $template = LabelLayoutTemplate::updateOrCreate(['slug' => $tplData['slug']], $tplData);
            if (! empty($btIds)) {
                $template->businessTypes()->syncWithoutDetaching($btIds);
            }
        }
    }
}
