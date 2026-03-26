<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Enums\WidgetCategory;
use App\Domain\ContentOnboarding\Models\LayoutWidget;
use App\Domain\ContentOnboarding\Models\MarketplaceCategory;
use Illuminate\Database\Seeder;

class LayoutWidgetSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWidgets();
        $this->seedMarketplaceCategories();
    }

    private function seedWidgets(): void
    {
        $widgets = [
            // ── Core Widgets ──
            [
                'slug' => 'product_grid',
                'name' => 'Product Grid',
                'name_ar' => 'شبكة المنتجات',
                'description' => 'Displays products in a grid layout with images and prices',
                'description_ar' => 'يعرض المنتجات في شبكة مع الصور والأسعار',
                'category' => WidgetCategory::Core,
                'icon' => 'heroicon-o-squares-2x2',
                'default_width' => 12,
                'default_height' => 10,
                'min_width' => 6,
                'min_height' => 4,
                'max_width' => 24,
                'max_height' => 16,
                'is_required' => true,
                'properties_schema' => [
                    'columns' => ['type' => 'integer', 'min' => 2, 'max' => 8, 'default' => 4],
                    'show_images' => ['type' => 'boolean', 'default' => true],
                    'show_prices' => ['type' => 'boolean', 'default' => true],
                    'show_stock' => ['type' => 'boolean', 'default' => false],
                ],
                'default_properties' => ['columns' => 4, 'show_images' => true, 'show_prices' => true, 'show_stock' => false],
                'sort_order' => 1,
            ],
            [
                'slug' => 'cart_panel',
                'name' => 'Cart Panel',
                'name_ar' => 'لوحة السلة',
                'description' => 'Shopping cart with line items, quantities, and totals',
                'description_ar' => 'سلة التسوق مع العناصر والكميات والإجماليات',
                'category' => WidgetCategory::Core,
                'icon' => 'heroicon-o-shopping-cart',
                'default_width' => 8,
                'default_height' => 14,
                'min_width' => 6,
                'min_height' => 6,
                'max_width' => 12,
                'max_height' => 16,
                'is_required' => true,
                'properties_schema' => [
                    'display_mode' => ['type' => 'select', 'options' => ['compact', 'detailed'], 'default' => 'detailed'],
                    'show_discounts' => ['type' => 'boolean', 'default' => true],
                    'show_tax' => ['type' => 'boolean', 'default' => true],
                ],
                'default_properties' => ['display_mode' => 'detailed', 'show_discounts' => true, 'show_tax' => true],
                'sort_order' => 2,
            ],
            [
                'slug' => 'payment_buttons',
                'name' => 'Payment Buttons',
                'name_ar' => 'أزرار الدفع',
                'description' => 'Payment method selection buttons (cash, card, etc.)',
                'description_ar' => 'أزرار اختيار طريقة الدفع (نقد، بطاقة، إلخ)',
                'category' => WidgetCategory::Core,
                'icon' => 'heroicon-o-credit-card',
                'default_width' => 8,
                'default_height' => 2,
                'min_width' => 4,
                'min_height' => 2,
                'max_width' => 12,
                'max_height' => 4,
                'is_required' => true,
                'properties_schema' => [
                    'methods' => ['type' => 'array', 'default' => ['cash', 'card', 'wallet']],
                    'layout' => ['type' => 'select', 'options' => ['horizontal', 'vertical', 'grid'], 'default' => 'horizontal'],
                ],
                'default_properties' => ['methods' => ['cash', 'card', 'wallet'], 'layout' => 'horizontal'],
                'sort_order' => 3,
            ],

            // ── Commerce Widgets ──
            [
                'slug' => 'category_bar',
                'name' => 'Category Bar',
                'name_ar' => 'شريط الفئات',
                'description' => 'Horizontal or vertical category navigation bar',
                'description_ar' => 'شريط تنقل الفئات أفقي أو عمودي',
                'category' => WidgetCategory::Commerce,
                'icon' => 'heroicon-o-tag',
                'default_width' => 24,
                'default_height' => 2,
                'min_width' => 6,
                'min_height' => 1,
                'max_width' => 24,
                'max_height' => 4,
                'properties_schema' => [
                    'style' => ['type' => 'select', 'options' => ['tabs', 'sidebar', 'icons', 'pills'], 'default' => 'tabs'],
                    'show_icons' => ['type' => 'boolean', 'default' => true],
                    'show_counts' => ['type' => 'boolean', 'default' => false],
                ],
                'default_properties' => ['style' => 'tabs', 'show_icons' => true, 'show_counts' => false],
                'sort_order' => 10,
            ],
            [
                'slug' => 'search_bar',
                'name' => 'Search Bar',
                'name_ar' => 'شريط البحث',
                'description' => 'Product search with barcode scanner support',
                'description_ar' => 'بحث المنتجات مع دعم ماسح الباركود',
                'category' => WidgetCategory::Commerce,
                'icon' => 'heroicon-o-magnifying-glass',
                'default_width' => 12,
                'default_height' => 1,
                'min_width' => 4,
                'min_height' => 1,
                'max_width' => 24,
                'max_height' => 2,
                'properties_schema' => [
                    'placeholder' => ['type' => 'string', 'default' => 'Search products...'],
                    'show_barcode_button' => ['type' => 'boolean', 'default' => true],
                    'auto_focus' => ['type' => 'boolean', 'default' => false],
                ],
                'default_properties' => ['placeholder' => 'Search products...', 'show_barcode_button' => true, 'auto_focus' => false],
                'sort_order' => 11,
            ],
            [
                'slug' => 'customer_info',
                'name' => 'Customer Info',
                'name_ar' => 'معلومات العميل',
                'description' => 'Customer details panel with search and loyalty info',
                'description_ar' => 'لوحة تفاصيل العميل مع البحث ومعلومات الولاء',
                'category' => WidgetCategory::Commerce,
                'icon' => 'heroicon-o-user',
                'default_width' => 8,
                'default_height' => 3,
                'min_width' => 4,
                'min_height' => 2,
                'max_width' => 12,
                'max_height' => 6,
                'properties_schema' => [
                    'show_loyalty_points' => ['type' => 'boolean', 'default' => true],
                    'show_purchase_history' => ['type' => 'boolean', 'default' => false],
                    'compact_mode' => ['type' => 'boolean', 'default' => false],
                ],
                'default_properties' => ['show_loyalty_points' => true, 'show_purchase_history' => false, 'compact_mode' => false],
                'sort_order' => 12,
            ],
            [
                'slug' => 'order_summary',
                'name' => 'Order Summary',
                'name_ar' => 'ملخص الطلب',
                'description' => 'Compact order total summary with subtotal, tax, and discounts',
                'description_ar' => 'ملخص إجمالي الطلب مع المجموع الفرعي والضريبة والخصومات',
                'category' => WidgetCategory::Commerce,
                'icon' => 'heroicon-o-clipboard-document-list',
                'default_width' => 8,
                'default_height' => 3,
                'min_width' => 4,
                'min_height' => 2,
                'max_width' => 12,
                'max_height' => 6,
                'properties_schema' => [
                    'show_item_count' => ['type' => 'boolean', 'default' => true],
                    'show_savings' => ['type' => 'boolean', 'default' => true],
                ],
                'default_properties' => ['show_item_count' => true, 'show_savings' => true],
                'sort_order' => 13,
            ],
            [
                'slug' => 'quick_actions',
                'name' => 'Quick Actions',
                'name_ar' => 'الإجراءات السريعة',
                'description' => 'Configurable quick action buttons (hold, discount, refund, etc.)',
                'description_ar' => 'أزرار إجراءات سريعة قابلة للتخصيص (تعليق، خصم، استرجاع، إلخ)',
                'category' => WidgetCategory::Commerce,
                'icon' => 'heroicon-o-bolt',
                'default_width' => 8,
                'default_height' => 2,
                'min_width' => 4,
                'min_height' => 1,
                'max_width' => 24,
                'max_height' => 4,
                'properties_schema' => [
                    'actions' => ['type' => 'array', 'default' => ['hold', 'discount', 'refund', 'void']],
                    'layout' => ['type' => 'select', 'options' => ['horizontal', 'grid'], 'default' => 'horizontal'],
                ],
                'default_properties' => ['actions' => ['hold', 'discount', 'refund', 'void'], 'layout' => 'horizontal'],
                'sort_order' => 14,
            ],

            // ── Display Widgets ──
            [
                'slug' => 'receipt_preview',
                'name' => 'Receipt Preview',
                'name_ar' => 'معاينة الإيصال',
                'description' => 'Live receipt preview panel',
                'description_ar' => 'لوحة معاينة الإيصال المباشر',
                'category' => WidgetCategory::Display,
                'icon' => 'heroicon-o-document-text',
                'default_width' => 6,
                'default_height' => 8,
                'min_width' => 4,
                'min_height' => 4,
                'max_width' => 12,
                'max_height' => 16,
                'properties_schema' => [
                    'paper_size' => ['type' => 'select', 'options' => ['80mm', '58mm'], 'default' => '80mm'],
                ],
                'default_properties' => ['paper_size' => '80mm'],
                'sort_order' => 20,
            ],
            [
                'slug' => 'clock_widget',
                'name' => 'Clock',
                'name_ar' => 'الساعة',
                'description' => 'Digital clock with date display',
                'description_ar' => 'ساعة رقمية مع عرض التاريخ',
                'category' => WidgetCategory::Display,
                'icon' => 'heroicon-o-clock',
                'default_width' => 4,
                'default_height' => 2,
                'min_width' => 2,
                'min_height' => 1,
                'max_width' => 8,
                'max_height' => 4,
                'properties_schema' => [
                    'format_24h' => ['type' => 'boolean', 'default' => true],
                    'show_date' => ['type' => 'boolean', 'default' => true],
                    'show_hijri' => ['type' => 'boolean', 'default' => false],
                ],
                'default_properties' => ['format_24h' => true, 'show_date' => true, 'show_hijri' => false],
                'sort_order' => 21,
            ],
            [
                'slug' => 'store_branding',
                'name' => 'Store Branding',
                'name_ar' => 'العلامة التجارية',
                'description' => 'Store logo and branding display area',
                'description_ar' => 'منطقة عرض شعار المتجر والعلامة التجارية',
                'category' => WidgetCategory::Display,
                'icon' => 'heroicon-o-building-storefront',
                'default_width' => 4,
                'default_height' => 2,
                'min_width' => 2,
                'min_height' => 1,
                'max_width' => 12,
                'max_height' => 4,
                'properties_schema' => [
                    'show_logo' => ['type' => 'boolean', 'default' => true],
                    'show_store_name' => ['type' => 'boolean', 'default' => true],
                ],
                'default_properties' => ['show_logo' => true, 'show_store_name' => true],
                'sort_order' => 22,
            ],

            // ── Utility Widgets ──
            [
                'slug' => 'numpad',
                'name' => 'Numpad',
                'name_ar' => 'لوحة الأرقام',
                'description' => 'Numeric keypad for quantity and price entry',
                'description_ar' => 'لوحة مفاتيح رقمية لإدخال الكمية والسعر',
                'category' => WidgetCategory::Utility,
                'icon' => 'heroicon-o-calculator',
                'default_width' => 4,
                'default_height' => 5,
                'min_width' => 3,
                'min_height' => 4,
                'max_width' => 8,
                'max_height' => 8,
                'properties_schema' => [
                    'show_decimal' => ['type' => 'boolean', 'default' => true],
                    'show_backspace' => ['type' => 'boolean', 'default' => true],
                ],
                'default_properties' => ['show_decimal' => true, 'show_backspace' => true],
                'sort_order' => 30,
            ],
            [
                'slug' => 'custom_html',
                'name' => 'Custom HTML',
                'name_ar' => 'HTML مخصص',
                'description' => 'Custom HTML content block for notices, ads, or info',
                'description_ar' => 'كتلة محتوى HTML مخصصة للإشعارات أو الإعلانات أو المعلومات',
                'category' => WidgetCategory::Utility,
                'icon' => 'heroicon-o-code-bracket',
                'default_width' => 6,
                'default_height' => 3,
                'min_width' => 2,
                'min_height' => 1,
                'max_width' => 24,
                'max_height' => 16,
                'properties_schema' => [
                    'html_content' => ['type' => 'text', 'default' => ''],
                ],
                'default_properties' => ['html_content' => ''],
                'sort_order' => 31,
            ],
            [
                'slug' => 'held_orders',
                'name' => 'Held Orders',
                'name_ar' => 'الطلبات المعلقة',
                'description' => 'List of held/parked orders for quick recall',
                'description_ar' => 'قائمة الطلبات المعلقة للاسترجاع السريع',
                'category' => WidgetCategory::Utility,
                'icon' => 'heroicon-o-pause-circle',
                'default_width' => 6,
                'default_height' => 4,
                'min_width' => 4,
                'min_height' => 3,
                'max_width' => 12,
                'max_height' => 8,
                'properties_schema' => [
                    'max_display' => ['type' => 'integer', 'min' => 3, 'max' => 20, 'default' => 5],
                    'show_age' => ['type' => 'boolean', 'default' => true],
                ],
                'default_properties' => ['max_display' => 5, 'show_age' => true],
                'sort_order' => 32,
            ],
        ];

        foreach ($widgets as $data) {
            LayoutWidget::updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }
    }

    private function seedMarketplaceCategories(): void
    {
        $categories = [
            ['name' => 'Retail', 'name_ar' => 'التجزئة', 'slug' => 'retail', 'icon' => 'heroicon-o-shopping-bag', 'sort_order' => 1],
            ['name' => 'Restaurant', 'name_ar' => 'المطاعم', 'slug' => 'restaurant', 'icon' => 'heroicon-o-cake', 'sort_order' => 2],
            ['name' => 'Grocery', 'name_ar' => 'البقالة', 'slug' => 'grocery', 'icon' => 'heroicon-o-shopping-cart', 'sort_order' => 3],
            ['name' => 'Pharmacy', 'name_ar' => 'الصيدلية', 'slug' => 'pharmacy', 'icon' => 'heroicon-o-heart', 'sort_order' => 4],
            ['name' => 'Electronics', 'name_ar' => 'الإلكترونيات', 'slug' => 'electronics', 'icon' => 'heroicon-o-device-phone-mobile', 'sort_order' => 5],
            ['name' => 'Fashion', 'name_ar' => 'الأزياء', 'slug' => 'fashion', 'icon' => 'heroicon-o-sparkles', 'sort_order' => 6],
            ['name' => 'Services', 'name_ar' => 'الخدمات', 'slug' => 'services', 'icon' => 'heroicon-o-wrench-screwdriver', 'sort_order' => 7],
            ['name' => 'Minimal', 'name_ar' => 'بسيط', 'slug' => 'minimal', 'icon' => 'heroicon-o-minus', 'sort_order' => 8],
        ];

        foreach ($categories as $data) {
            MarketplaceCategory::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['is_active' => true]),
            );
        }
    }
}
