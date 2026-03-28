<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\SignageTemplate;
use Illuminate\Database\Seeder;

class ComprehensiveSignageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $bt = fn (string $slug) => BusinessType::where('slug', $slug)->first()?->id;

        $templates = [
            // ── 1. Menu Board Classic (existing, enhanced) ──
            [
                'name'              => 'Menu Board Classic',
                'name_ar'           => 'لوحة القائمة الكلاسيكية',
                'slug'              => 'menu_board_classic',
                'template_type'     => 'menu_board',
                'layout_config'     => [
                    ['region_id' => 'header', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 12, 'default_content' => 'Our Menu'],
                    ['region_id' => 'categories', 'type' => 'text', 'x' => 0, 'y' => 12, 'w' => 20, 'h' => 78, 'default_content' => ''],
                    ['region_id' => 'products', 'type' => 'product_grid', 'x' => 20, 'y' => 12, 'w' => 80, 'h' => 78, 'default_content' => ''],
                    ['region_id' => 'footer', 'type' => 'text', 'x' => 0, 'y' => 90, 'w' => 100, 'h' => 10, 'default_content' => 'بالعافية'],
                ],
                'placeholder_content' => ['header_text' => 'Our Menu', 'header_text_ar' => 'قائمتنا', 'footer_text' => 'Enjoy your meal!', 'footer_text_ar' => 'بالعافية!'],
                'background_color'    => '#1E293B',
                'text_color'          => '#F8FAFC',
                'font_family'         => 'Poppins',
                'transition_style'    => 'fade',
                'is_active'           => true,
                'business_types'      => ['restaurant', 'bakery'],
            ],
            // ── 2. Promo Slideshow (existing, enhanced) ──
            [
                'name'              => 'Promo Slideshow',
                'name_ar'           => 'عرض الترويج',
                'slug'              => 'promo_slideshow_default',
                'template_type'     => 'promo_slideshow',
                'layout_config'     => [
                    ['region_id' => 'slide_area', 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 80, 'default_content' => ''],
                    ['region_id' => 'ticker', 'type' => 'text', 'x' => 0, 'y' => 80, 'w' => 100, 'h' => 20, 'default_content' => ''],
                ],
                'placeholder_content' => ['slide_area' => '', 'ticker_text' => 'Special offers today!', 'ticker_text_ar' => 'عروض خاصة اليوم!'],
                'background_color'    => '#FFFFFF',
                'text_color'          => '#111827',
                'font_family'         => 'Inter',
                'transition_style'    => 'slide',
                'is_active'           => true,
                'business_types'      => ['restaurant', 'grocery', 'retail'],
            ],
            // ── 3. Queue Display – ticket/queue management ──
            [
                'name'              => 'Queue Ticket Display',
                'name_ar'           => 'شاشة أرقام الانتظار',
                'slug'              => 'queue_ticket_display',
                'template_type'     => 'queue_display',
                'layout_config'     => [
                    ['region_id' => 'header', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 10, 'default_content' => 'Now Serving'],
                    ['region_id' => 'current_number', 'type' => 'text', 'x' => 10, 'y' => 15, 'w' => 80, 'h' => 35, 'default_content' => '---'],
                    ['region_id' => 'counter_label', 'type' => 'text', 'x' => 20, 'y' => 52, 'w' => 60, 'h' => 8, 'default_content' => 'Counter 1'],
                    ['region_id' => 'upcoming', 'type' => 'text', 'x' => 5, 'y' => 65, 'w' => 90, 'h' => 25, 'default_content' => 'Next: ---'],
                    ['region_id' => 'clock', 'type' => 'clock', 'x' => 80, 'y' => 0, 'w' => 20, 'h' => 10, 'default_content' => ''],
                ],
                'placeholder_content' => [
                    'header_text_en' => 'Now Serving', 'header_text_ar' => 'يتم الخدمة الآن',
                    'counter_label_en' => 'Counter', 'counter_label_ar' => 'الكاونتر',
                    'upcoming_label_en' => 'Next', 'upcoming_label_ar' => 'التالي',
                ],
                'background_color'    => '#0F172A',
                'text_color'          => '#FFFFFF',
                'font_family'         => 'Roboto',
                'transition_style'    => 'none',
                'is_active'           => true,
                'business_types'      => ['pharmacy', 'service', 'restaurant'],
            ],
            // ── 4. Welcome Screen – greeting/branding ──
            [
                'name'              => 'Welcome Screen',
                'name_ar'           => 'شاشة الترحيب',
                'slug'              => 'welcome_screen_default',
                'template_type'     => 'welcome',
                'layout_config'     => [
                    ['region_id' => 'logo', 'type' => 'image', 'x' => 25, 'y' => 5, 'w' => 50, 'h' => 30, 'default_content' => ''],
                    ['region_id' => 'greeting', 'type' => 'text', 'x' => 10, 'y' => 40, 'w' => 80, 'h' => 15, 'default_content' => 'Welcome!'],
                    ['region_id' => 'subtitle', 'type' => 'text', 'x' => 15, 'y' => 58, 'w' => 70, 'h' => 10, 'default_content' => 'We are happy to serve you'],
                    ['region_id' => 'promo_banner', 'type' => 'image', 'x' => 10, 'y' => 72, 'w' => 80, 'h' => 20, 'default_content' => ''],
                ],
                'placeholder_content' => [
                    'greeting_en' => 'Welcome!', 'greeting_ar' => 'أهلاً وسهلاً!',
                    'subtitle_en' => 'We are happy to serve you', 'subtitle_ar' => 'يسعدنا خدمتك',
                ],
                'background_color'    => '#FFFFFF',
                'text_color'          => '#111827',
                'font_family'         => 'Poppins',
                'transition_style'    => 'fade',
                'is_active'           => true,
                'business_types'      => ['retail', 'restaurant', 'grocery', 'jewelry', 'flower_shop'],
            ],
            // ── 5. Daily Specials Board – restaurant/bakery specials ──
            [
                'name'              => 'Daily Specials Board',
                'name_ar'           => 'لوحة العروض اليومية',
                'slug'              => 'daily_specials_board',
                'template_type'     => 'menu_board',
                'layout_config'     => [
                    ['region_id' => 'title', 'type' => 'text', 'x' => 5, 'y' => 2, 'w' => 90, 'h' => 12, 'default_content' => "Today's Specials"],
                    ['region_id' => 'special_1', 'type' => 'product_grid', 'x' => 5, 'y' => 16, 'w' => 45, 'h' => 38, 'default_content' => ''],
                    ['region_id' => 'special_2', 'type' => 'product_grid', 'x' => 50, 'y' => 16, 'w' => 45, 'h' => 38, 'default_content' => ''],
                    ['region_id' => 'special_3', 'type' => 'product_grid', 'x' => 5, 'y' => 56, 'w' => 45, 'h' => 38, 'default_content' => ''],
                    ['region_id' => 'special_4', 'type' => 'product_grid', 'x' => 50, 'y' => 56, 'w' => 45, 'h' => 38, 'default_content' => ''],
                ],
                'placeholder_content' => ['title_en' => "Today's Specials", 'title_ar' => 'عروض اليوم'],
                'background_color'    => '#422006',
                'text_color'          => '#FEF3C7',
                'font_family'         => 'Playfair Display',
                'transition_style'    => 'fade',
                'is_active'           => true,
                'business_types'      => ['restaurant', 'bakery'],
            ],
            // ── 6. Info & Announcements Board ──
            [
                'name'              => 'Info & Announcements',
                'name_ar'           => 'لوحة الإعلانات والمعلومات',
                'slug'              => 'info_announcements_board',
                'template_type'     => 'info_board',
                'layout_config'     => [
                    ['region_id' => 'header', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 10, 'default_content' => 'Announcements'],
                    ['region_id' => 'main_content', 'type' => 'text', 'x' => 5, 'y' => 12, 'w' => 90, 'h' => 50, 'default_content' => ''],
                    ['region_id' => 'side_image', 'type' => 'image', 'x' => 5, 'y' => 65, 'w' => 45, 'h' => 30, 'default_content' => ''],
                    ['region_id' => 'side_text', 'type' => 'text', 'x' => 52, 'y' => 65, 'w' => 43, 'h' => 30, 'default_content' => ''],
                ],
                'placeholder_content' => [
                    'header_en' => 'Announcements', 'header_ar' => 'إعلانات',
                    'main_content_en' => 'Important updates will appear here.',
                    'main_content_ar' => 'ستظهر التحديثات المهمة هنا.',
                ],
                'background_color'    => '#F0F9FF',
                'text_color'          => '#1E3A5F',
                'font_family'         => 'Inter',
                'transition_style'    => 'fade',
                'is_active'           => true,
                'business_types'      => ['retail', 'pharmacy', 'service'],
            ],
            // ── 7. Dynamic Price Board – real-time price display ──
            [
                'name'              => 'Dynamic Price Board',
                'name_ar'           => 'لوحة الأسعار الديناميكية',
                'slug'              => 'dynamic_price_board',
                'template_type'     => 'menu_board',
                'layout_config'     => [
                    ['region_id' => 'header', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 8, 'default_content' => 'Price List'],
                    ['region_id' => 'price_table', 'type' => 'product_grid', 'x' => 0, 'y' => 8, 'w' => 100, 'h' => 82, 'default_content' => ''],
                    ['region_id' => 'footer', 'type' => 'text', 'x' => 0, 'y' => 90, 'w' => 100, 'h' => 10, 'default_content' => 'Prices include 15% VAT'],
                ],
                'placeholder_content' => [
                    'header_en' => 'Price List', 'header_ar' => 'قائمة الأسعار',
                    'footer_en' => 'All prices include 15% VAT', 'footer_ar' => 'جميع الأسعار شاملة 15% ضريبة القيمة المضافة',
                ],
                'background_color'    => '#18181B',
                'text_color'          => '#FAFAFA',
                'font_family'         => 'Roboto Mono',
                'transition_style'    => 'none',
                'is_active'           => true,
                'business_types'      => ['grocery', 'mobile_shop', 'electronics'],
            ],
            // ── 8. Happy Hour Timer – countdown/promotional timer ──
            [
                'name'              => 'Happy Hour Timer',
                'name_ar'           => 'مؤقت ساعة التخفيضات',
                'slug'              => 'happy_hour_timer',
                'template_type'     => 'promo_slideshow',
                'layout_config'     => [
                    ['region_id' => 'title', 'type' => 'text', 'x' => 10, 'y' => 5, 'w' => 80, 'h' => 15, 'default_content' => '🔥 HAPPY HOUR 🔥'],
                    ['region_id' => 'countdown', 'type' => 'clock', 'x' => 15, 'y' => 22, 'w' => 70, 'h' => 25, 'default_content' => ''],
                    ['region_id' => 'deal_items', 'type' => 'product_grid', 'x' => 5, 'y' => 50, 'w' => 90, 'h' => 35, 'default_content' => ''],
                    ['region_id' => 'footer', 'type' => 'text', 'x' => 10, 'y' => 88, 'w' => 80, 'h' => 10, 'default_content' => 'Limited time offer!'],
                ],
                'placeholder_content' => [
                    'title_en' => '🔥 HAPPY HOUR 🔥', 'title_ar' => '🔥 ساعة التخفيضات 🔥',
                    'footer_en' => 'Limited time offer!', 'footer_ar' => 'عرض لفترة محدودة!',
                ],
                'background_color'    => '#7F1D1D',
                'text_color'          => '#FECACA',
                'font_family'         => 'Poppins',
                'transition_style'    => 'slide',
                'is_active'           => true,
                'business_types'      => ['restaurant', 'retail', 'grocery'],
            ],
            // ── 9. Product Showcase – rotating product highlights ──
            [
                'name'              => 'Product Showcase',
                'name_ar'           => 'عرض المنتجات المميزة',
                'slug'              => 'product_showcase',
                'template_type'     => 'promo_slideshow',
                'layout_config'     => [
                    ['region_id' => 'product_image', 'type' => 'image', 'x' => 5, 'y' => 5, 'w' => 55, 'h' => 70, 'default_content' => ''],
                    ['region_id' => 'product_name', 'type' => 'text', 'x' => 62, 'y' => 10, 'w' => 35, 'h' => 15, 'default_content' => 'Featured Product'],
                    ['region_id' => 'product_desc', 'type' => 'text', 'x' => 62, 'y' => 28, 'w' => 35, 'h' => 25, 'default_content' => ''],
                    ['region_id' => 'product_price', 'type' => 'text', 'x' => 62, 'y' => 56, 'w' => 35, 'h' => 15, 'default_content' => ''],
                    ['region_id' => 'cta', 'type' => 'text', 'x' => 62, 'y' => 73, 'w' => 35, 'h' => 10, 'default_content' => 'Ask our staff!'],
                    ['region_id' => 'brand_footer', 'type' => 'text', 'x' => 0, 'y' => 88, 'w' => 100, 'h' => 12, 'default_content' => ''],
                ],
                'placeholder_content' => [
                    'product_name_en' => 'Featured Product', 'product_name_ar' => 'المنتج المميز',
                    'cta_en' => 'Ask our staff!', 'cta_ar' => 'اسأل موظفينا!',
                ],
                'background_color'    => '#FFFFFF',
                'text_color'          => '#111827',
                'font_family'         => 'Inter',
                'transition_style'    => 'slide',
                'is_active'           => true,
                'business_types'      => ['retail', 'electronics', 'jewelry', 'mobile_shop'],
            ],
            // ── 10. Digital Menu Vertical – portrait orientation menu ──
            [
                'name'              => 'Digital Menu Vertical',
                'name_ar'           => 'قائمة رقمية عمودية',
                'slug'              => 'digital_menu_vertical',
                'template_type'     => 'menu_board',
                'layout_config'     => [
                    ['region_id' => 'logo', 'type' => 'image', 'x' => 30, 'y' => 1, 'w' => 40, 'h' => 8, 'default_content' => ''],
                    ['region_id' => 'title', 'type' => 'text', 'x' => 5, 'y' => 10, 'w' => 90, 'h' => 5, 'default_content' => 'Menu'],
                    ['region_id' => 'section_1', 'type' => 'product_grid', 'x' => 3, 'y' => 16, 'w' => 94, 'h' => 25, 'default_content' => ''],
                    ['region_id' => 'section_2', 'type' => 'product_grid', 'x' => 3, 'y' => 42, 'w' => 94, 'h' => 25, 'default_content' => ''],
                    ['region_id' => 'section_3', 'type' => 'product_grid', 'x' => 3, 'y' => 68, 'w' => 94, 'h' => 25, 'default_content' => ''],
                    ['region_id' => 'footer', 'type' => 'text', 'x' => 5, 'y' => 94, 'w' => 90, 'h' => 5, 'default_content' => ''],
                ],
                'placeholder_content' => [
                    'title_en' => 'Our Menu', 'title_ar' => 'قائمتنا',
                    'section_1_label' => 'Appetizers / المقبلات',
                    'section_2_label' => 'Main Course / الأطباق الرئيسية',
                    'section_3_label' => 'Desserts & Drinks / الحلويات والمشروبات',
                ],
                'background_color'    => '#292524',
                'text_color'          => '#FAFAF9',
                'font_family'         => 'Noto Sans Arabic',
                'transition_style'    => 'fade',
                'is_active'           => true,
                'business_types'      => ['restaurant', 'bakery'],
            ],
        ];

        foreach ($templates as $tplData) {
            $btSlugs = $tplData['business_types'] ?? [];
            unset($tplData['business_types']);

            $btIds = array_filter(array_map(fn ($s) => $bt($s), $btSlugs));

            $template = SignageTemplate::updateOrCreate(['slug' => $tplData['slug']], $tplData);
            if (! empty($btIds)) {
                $template->businessTypes()->syncWithoutDetaching($btIds);
            }
        }
    }
}
