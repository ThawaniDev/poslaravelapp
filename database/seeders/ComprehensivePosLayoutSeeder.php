<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\LayoutWidget;
use App\Domain\ContentOnboarding\Models\LayoutWidgetPlacement;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use Illuminate\Database\Seeder;

class ComprehensivePosLayoutSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = BusinessType::where('is_active', true)->get()->keyBy('slug');
        $widgets       = LayoutWidget::all()->keyBy('slug');

        // ── Helper: widget id by slug ──
        $wid = fn (string $slug) => $widgets->get($slug)?->id;

        // ── Common widget placements per layout type ──
        // Each placement: [slug, x, y, w, h, properties_override, z]
        $coreRight = fn () => [
            ['search_bar',       0, 0, 16, 1, [], 1],
            ['category_bar',     0, 1, 16, 2, [], 1],
            ['product_grid',     0, 3, 16, 13, [], 1],
            ['cart_panel',       16, 0, 8, 10, [], 2],
            ['order_summary',    16, 10, 8, 2, [], 2],
            ['payment_buttons',  16, 12, 8, 2, [], 2],
            ['quick_actions',    16, 14, 8, 2, [], 2],
        ];

        $compactList = fn () => [
            ['search_bar',      0, 0, 14, 1, [], 1],
            ['category_bar',    0, 1, 4, 15, ['style' => 'sidebar'], 1],
            ['product_grid',    4, 1, 10, 15, ['columns' => 1, 'show_images' => false], 1],
            ['cart_panel',      14, 0, 10, 10, ['display_mode' => 'compact'], 2],
            ['order_summary',   14, 10, 10, 2, [], 2],
            ['payment_buttons', 14, 12, 10, 2, [], 2],
            ['quick_actions',   14, 14, 10, 2, [], 2],
        ];

        // ── Business-type-specific layout definitions (3rd layout per BT) ──
        $specialized = [
            'retail' => [
                'key'    => 'retail_barcode_scanner',
                'name'   => 'Barcode Scanner Focus',
                'name_ar' => 'التركيز على ماسح الباركود',
                'desc'   => 'Optimized layout for high-volume barcode scanning in retail stores',
                'config' => ['layout_type' => 'scan_focused', 'cart_position' => 'right', 'cart_width' => 40, 'show_categories' => false, 'product_display' => 'list', 'product_columns' => 1, 'show_images' => false, 'quick_actions' => ['discount', 'hold', 'refund', 'void'], 'barcode_auto_submit' => true],
                'placements' => [
                    ['search_bar', 0, 0, 24, 2, ['auto_focus' => true, 'show_barcode_button' => true], 1],
                    ['product_grid', 0, 2, 14, 8, ['columns' => 1, 'show_images' => false], 1],
                    ['cart_panel', 14, 0, 10, 10, ['display_mode' => 'compact'], 2],
                    ['numpad', 0, 10, 6, 6, [], 1],
                    ['order_summary', 6, 10, 8, 3, [], 1],
                    ['payment_buttons', 14, 10, 10, 3, [], 2],
                    ['quick_actions', 14, 13, 10, 3, [], 2],
                ],
            ],
            'restaurant' => [
                'key'    => 'restaurant_table_management',
                'name'   => 'Table Management',
                'name_ar' => 'إدارة الطاولات',
                'desc'   => 'Restaurant layout with interactive table map, order tracking, and dine-in management',
                'config' => ['layout_type' => 'table_management', 'cart_position' => 'right', 'cart_width' => 30, 'show_categories' => true, 'category_style' => 'icons', 'product_display' => 'grid', 'product_columns' => 3, 'show_images' => true, 'quick_actions' => ['hold', 'split_bill', 'void', 'print_kitchen']],
                'placements' => [
                    ['table_map', 0, 0, 10, 8, [], 1],
                    ['category_bar', 10, 0, 6, 2, ['style' => 'icons'], 1],
                    ['product_grid', 10, 2, 6, 6, ['columns' => 3], 1],
                    ['search_bar', 10, 8, 6, 1, [], 1],
                    ['cart_panel', 16, 0, 8, 10, [], 2],
                    ['order_summary', 0, 8, 10, 2, [], 1],
                    ['payment_buttons', 16, 10, 8, 3, [], 2],
                    ['quick_actions', 16, 13, 8, 3, [], 2],
                    ['held_orders', 0, 10, 10, 6, [], 1],
                ],
            ],
            'pharmacy' => [
                'key'    => 'pharmacy_prescription',
                'name'   => 'Prescription Counter',
                'name_ar' => 'عداد الوصفات الطبية',
                'desc'   => 'Pharmacy layout optimized for prescription lookups, drug schedules, and patient info',
                'config' => ['layout_type' => 'prescription', 'cart_position' => 'right', 'cart_width' => 35, 'show_categories' => true, 'category_style' => 'pills', 'product_display' => 'list', 'product_columns' => 1, 'show_images' => true, 'quick_actions' => ['discount', 'hold', 'refund', 'print_label'], 'show_drug_schedule' => true, 'show_expiry_warning' => true],
                'placements' => [
                    ['search_bar', 0, 0, 16, 2, ['placeholder' => 'Search medicine by name, barcode, or generic...', 'auto_focus' => true], 1],
                    ['category_bar', 0, 2, 16, 2, ['style' => 'pills'], 1],
                    ['product_grid', 0, 4, 16, 8, ['columns' => 1, 'show_stock' => true], 1],
                    ['customer_info', 0, 12, 8, 4, ['show_purchase_history' => true], 1],
                    ['staff_notes', 8, 12, 8, 4, [], 1],
                    ['cart_panel', 16, 0, 8, 10, [], 2],
                    ['order_summary', 16, 10, 8, 2, [], 2],
                    ['payment_buttons', 16, 12, 8, 2, [], 2],
                    ['quick_actions', 16, 14, 8, 2, [], 2],
                ],
            ],
            'grocery' => [
                'key'    => 'grocery_weight_scale',
                'name'   => 'Weight & Scale',
                'name_ar' => 'الوزن والميزان',
                'desc'   => 'Grocery layout with integrated weight scale display and produce lookup',
                'config' => ['layout_type' => 'weight_integrated', 'cart_position' => 'right', 'cart_width' => 35, 'show_categories' => true, 'category_style' => 'tabs', 'product_display' => 'grid', 'product_columns' => 4, 'show_images' => true, 'quick_actions' => ['discount', 'hold', 'void', 'weigh'], 'scale_integration' => true],
                'placements' => [
                    ['search_bar', 0, 0, 16, 1, ['show_barcode_button' => true], 1],
                    ['category_bar', 0, 1, 16, 2, [], 1],
                    ['product_grid', 0, 3, 16, 9, ['columns' => 4, 'show_stock' => true], 1],
                    ['numpad', 0, 12, 6, 4, [], 1],
                    ['custom_html', 6, 12, 10, 4, ['html_content' => '<div class="scale-display">Scale: 0.000 kg</div>'], 1],
                    ['cart_panel', 16, 0, 8, 10, [], 2],
                    ['order_summary', 16, 10, 8, 2, [], 2],
                    ['payment_buttons', 16, 12, 8, 2, [], 2],
                    ['quick_actions', 16, 14, 8, 2, [], 2],
                ],
            ],
            'jewelry' => [
                'key'    => 'jewelry_showcase',
                'name'   => 'Luxury Showcase',
                'name_ar' => 'معرض الفخامة',
                'desc'   => 'Luxury jewellery layout with large product imagery, karat details, and weight display',
                'config' => ['layout_type' => 'luxury_grid', 'cart_position' => 'right', 'cart_width' => 30, 'show_categories' => true, 'category_style' => 'icons', 'product_display' => 'grid', 'product_columns' => 3, 'show_images' => true, 'quick_actions' => ['discount', 'hold', 'gift_wrap', 'certificate'], 'show_gold_rate' => true, 'show_weight' => true],
                'placements' => [
                    ['store_branding', 0, 0, 6, 2, [], 1],
                    ['search_bar', 6, 0, 10, 1, [], 1],
                    ['custom_html', 6, 1, 10, 1, ['html_content' => '<div class="gold-rate">Gold 24K: --- SAR/g</div>'], 1],
                    ['category_bar', 0, 2, 16, 2, ['style' => 'icons'], 1],
                    ['product_grid', 0, 4, 16, 12, ['columns' => 3, 'show_images' => true], 1],
                    ['cart_panel', 16, 0, 8, 10, [], 2],
                    ['customer_info', 16, 10, 8, 2, ['show_loyalty_points' => true], 2],
                    ['payment_buttons', 16, 12, 8, 2, [], 2],
                    ['quick_actions', 16, 14, 8, 2, [], 2],
                ],
            ],
            'mobile_shop' => [
                'key'    => 'mobile_shop_imei',
                'name'   => 'IMEI Tracking',
                'name_ar' => 'تتبع IMEI',
                'desc'   => 'Mobile shop layout with IMEI lookup, device tracking, and accessory recommendations',
                'config' => ['layout_type' => 'imei_focused', 'cart_position' => 'right', 'cart_width' => 35, 'show_categories' => true, 'category_style' => 'tabs', 'product_display' => 'list', 'product_columns' => 1, 'show_images' => true, 'quick_actions' => ['discount', 'hold', 'warranty_check', 'buyback'], 'imei_tracking' => true],
                'placements' => [
                    ['search_bar', 0, 0, 16, 2, ['placeholder' => 'Search by IMEI, model, or barcode...', 'auto_focus' => true], 1],
                    ['category_bar', 0, 2, 16, 2, [], 1],
                    ['product_grid', 0, 4, 16, 8, ['columns' => 2, 'show_stock' => true], 1],
                    ['customer_info', 0, 12, 8, 4, ['show_purchase_history' => true], 1],
                    ['recent_transactions', 8, 12, 8, 4, ['max_rows' => 5], 1],
                    ['cart_panel', 16, 0, 8, 10, [], 2],
                    ['order_summary', 16, 10, 8, 2, [], 2],
                    ['payment_buttons', 16, 12, 8, 2, [], 2],
                    ['quick_actions', 16, 14, 8, 2, [], 2],
                ],
            ],
            'flower_shop' => [
                'key'    => 'flower_shop_arrangement',
                'name'   => 'Floral Arrangement Builder',
                'name_ar' => 'منشئ تنسيقات الزهور',
                'desc'   => 'Flower shop layout with arrangement builder, freshness indicators, and gift messaging',
                'config' => ['layout_type' => 'arrangement_builder', 'cart_position' => 'right', 'cart_width' => 35, 'show_categories' => true, 'category_style' => 'icons', 'product_display' => 'grid', 'product_columns' => 3, 'show_images' => true, 'quick_actions' => ['discount', 'gift_card', 'delivery', 'custom_message'], 'show_freshness' => true],
                'placements' => [
                    ['store_branding', 0, 0, 6, 2, [], 1],
                    ['search_bar', 6, 0, 10, 1, [], 1],
                    ['clock_widget', 6, 1, 4, 1, [], 1],
                    ['weather_widget', 10, 1, 6, 1, [], 1],
                    ['category_bar', 0, 2, 16, 2, ['style' => 'icons'], 1],
                    ['product_grid', 0, 4, 16, 12, ['columns' => 3, 'show_images' => true], 1],
                    ['cart_panel', 16, 0, 8, 10, [], 2],
                    ['customer_info', 16, 10, 8, 2, [], 2],
                    ['payment_buttons', 16, 12, 8, 2, [], 2],
                    ['quick_actions', 16, 14, 8, 2, [], 2],
                ],
            ],
            'bakery' => [
                'key'    => 'bakery_custom_orders',
                'name'   => 'Custom Cake Orders',
                'name_ar' => 'طلبات الكيك المخصصة',
                'desc'   => 'Bakery layout with custom cake builder, order queue, and daily production tracking',
                'config' => ['layout_type' => 'custom_order', 'cart_position' => 'right', 'cart_width' => 35, 'show_categories' => true, 'category_style' => 'tabs', 'product_display' => 'grid', 'product_columns' => 3, 'show_images' => true, 'quick_actions' => ['discount', 'hold', 'custom_cake', 'gift_box'], 'show_production_queue' => true],
                'placements' => [
                    ['search_bar', 0, 0, 12, 1, [], 1],
                    ['clock_widget', 12, 0, 4, 1, [], 1],
                    ['category_bar', 0, 1, 16, 2, [], 1],
                    ['product_grid', 0, 3, 16, 9, ['columns' => 3, 'show_images' => true], 1],
                    ['held_orders', 0, 12, 8, 4, ['max_display' => 8], 1],
                    ['staff_notes', 8, 12, 8, 4, [], 1],
                    ['cart_panel', 16, 0, 8, 10, [], 2],
                    ['order_summary', 16, 10, 8, 2, [], 2],
                    ['payment_buttons', 16, 12, 8, 2, [], 2],
                    ['quick_actions', 16, 14, 8, 2, [], 2],
                ],
            ],
            'service' => [
                'key'    => 'service_appointment',
                'name'   => 'Appointment Booking',
                'name_ar' => 'حجز المواعيد',
                'desc'   => 'Service business layout with appointment scheduling, service menu, and customer history',
                'config' => ['layout_type' => 'appointment', 'cart_position' => 'right', 'cart_width' => 35, 'show_categories' => true, 'category_style' => 'sidebar', 'product_display' => 'list', 'product_columns' => 1, 'show_images' => false, 'quick_actions' => ['discount', 'hold', 'reschedule', 'no_show'], 'show_calendar' => true],
                'placements' => [
                    ['search_bar', 0, 0, 16, 1, [], 1],
                    ['category_bar', 0, 1, 4, 11, ['style' => 'sidebar'], 1],
                    ['product_grid', 4, 1, 12, 7, ['columns' => 1, 'show_images' => false], 1],
                    ['customer_info', 4, 8, 6, 4, ['show_purchase_history' => true, 'compact_mode' => false], 1],
                    ['clock_widget', 10, 8, 6, 2, ['show_hijri' => true], 1],
                    ['staff_notes', 10, 10, 6, 2, [], 1],
                    ['held_orders', 0, 12, 16, 4, ['max_display' => 10], 1],
                    ['cart_panel', 16, 0, 8, 10, [], 2],
                    ['order_summary', 16, 10, 8, 2, [], 2],
                    ['payment_buttons', 16, 12, 8, 2, [], 2],
                    ['quick_actions', 16, 14, 8, 2, [], 2],
                ],
            ],
            'custom' => [
                'key'    => 'custom_minimal',
                'name'   => 'Minimalist',
                'name_ar' => 'بسيط',
                'desc'   => 'Clean minimal layout with only essential widgets for custom business types',
                'config' => ['layout_type' => 'minimal', 'cart_position' => 'right', 'cart_width' => 40, 'show_categories' => false, 'product_display' => 'list', 'product_columns' => 1, 'show_images' => false, 'quick_actions' => ['discount', 'hold'], 'minimal_mode' => true],
                'placements' => [
                    ['search_bar', 0, 0, 14, 2, ['auto_focus' => true], 1],
                    ['product_grid', 0, 2, 14, 12, ['columns' => 1, 'show_images' => false], 1],
                    ['numpad', 0, 14, 6, 2, [], 1],
                    ['cart_panel', 14, 0, 10, 10, ['display_mode' => 'compact'], 2],
                    ['order_summary', 14, 10, 10, 2, [], 2],
                    ['payment_buttons', 14, 12, 10, 2, [], 2],
                    ['quick_actions', 14, 14, 10, 2, [], 2],
                ],
            ],
        ];

        foreach ($businessTypes as $slug => $bt) {
            // ── 1. Standard Grid ──
            $layout1 = PosLayoutTemplate::updateOrCreate(
                ['layout_key' => $slug . '_standard_grid'],
                [
                    'business_type_id'  => $bt->id,
                    'name'              => 'Standard Grid',
                    'name_ar'           => 'الشبكة القياسية',
                    'description'       => "Default grid layout for {$bt->name} with product images and right-side cart",
                    'config'            => [
                        'layout_type' => 'grid', 'cart_position' => 'right', 'cart_width' => 35,
                        'show_categories' => true, 'category_style' => 'tabs', 'product_display' => 'grid',
                        'product_columns' => 4, 'show_images' => true,
                        'quick_actions' => ['discount', 'hold', 'refund'],
                        'payment_buttons' => ['cash', 'card', 'wallet'],
                    ],
                    'is_default'        => true,
                    'is_active'         => true,
                    'sort_order'        => 0,
                    'canvas_columns'    => 24,
                    'canvas_rows'       => 16,
                    'canvas_gap_px'     => 4,
                    'canvas_padding_px' => 8,
                    'breakpoints'       => ['tablet' => ['columns' => 12, 'rows' => 12], 'mobile' => ['columns' => 6, 'rows' => 8]],
                    'version'           => '1.0.0',
                    'is_locked'         => false,
                    'published_at'      => now(),
                ],
            );
            $this->syncPlacements($layout1, $coreRight(), $wid);

            // ── 2. Compact List ──
            $layout2 = PosLayoutTemplate::updateOrCreate(
                ['layout_key' => $slug . '_compact_list'],
                [
                    'business_type_id'  => $bt->id,
                    'name'              => 'Compact List',
                    'name_ar'           => 'القائمة المدمجة',
                    'description'       => "Compact list layout for {$bt->name} with sidebar categories and no images",
                    'config'            => [
                        'layout_type' => 'split', 'cart_position' => 'right', 'cart_width' => 40,
                        'show_categories' => true, 'category_style' => 'sidebar', 'product_display' => 'list',
                        'product_columns' => 1, 'show_images' => false,
                        'quick_actions' => ['discount', 'hold'],
                        'payment_buttons' => ['cash', 'card'],
                    ],
                    'is_default'        => false,
                    'is_active'         => true,
                    'sort_order'        => 1,
                    'canvas_columns'    => 24,
                    'canvas_rows'       => 16,
                    'canvas_gap_px'     => 4,
                    'canvas_padding_px' => 8,
                    'breakpoints'       => ['tablet' => ['columns' => 12, 'rows' => 12]],
                    'version'           => '1.0.0',
                    'is_locked'         => false,
                    'published_at'      => now(),
                ],
            );
            $this->syncPlacements($layout2, $compactList(), $wid);

            // ── 3. Specialized Layout ──
            $spec = $specialized[$slug] ?? null;
            if ($spec) {
                $layout3 = PosLayoutTemplate::updateOrCreate(
                    ['layout_key' => $spec['key']],
                    [
                        'business_type_id'  => $bt->id,
                        'name'              => $spec['name'],
                        'name_ar'           => $spec['name_ar'],
                        'description'       => $spec['desc'],
                        'config'            => $spec['config'],
                        'is_default'        => false,
                        'is_active'         => true,
                        'sort_order'        => 2,
                        'canvas_columns'    => 24,
                        'canvas_rows'       => 16,
                        'canvas_gap_px'     => 4,
                        'canvas_padding_px' => 8,
                        'breakpoints'       => ['tablet' => ['columns' => 12, 'rows' => 12]],
                        'version'           => '1.0.0',
                        'is_locked'         => false,
                        'published_at'      => now(),
                    ],
                );
                $this->syncPlacements($layout3, $spec['placements'], $wid);
            }
        }
    }

    /**
     * Sync widget placements for a layout template.
     *
     * @param  PosLayoutTemplate  $layout
     * @param  array  $placements  [[slug, x, y, w, h, props, z], ...]
     * @param  \Closure  $wid  widget-id resolver
     */
    private function syncPlacements(PosLayoutTemplate $layout, array $placements, \Closure $wid): void
    {
        // Remove old placements for idempotency
        LayoutWidgetPlacement::where('pos_layout_template_id', $layout->id)->delete();

        foreach ($placements as $i => $p) {
            [$slug, $x, $y, $w, $h, $props, $z] = $p;
            $widgetId = $wid($slug);
            if (! $widgetId) {
                continue;
            }

            LayoutWidgetPlacement::create([
                'pos_layout_template_id' => $layout->id,
                'layout_widget_id'       => $widgetId,
                'instance_key'           => $slug . '_' . $i,
                'grid_x'                 => $x,
                'grid_y'                 => $y,
                'grid_w'                 => $w,
                'grid_h'                 => $h,
                'z_index'                => $z,
                'properties'             => ! empty($props) ? $props : [],
                'is_visible'             => true,
            ]);
        }
    }
}
