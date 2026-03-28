<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Models\MarketplaceCategory;
use Illuminate\Database\Seeder;

class ComprehensiveMarketplaceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'           => 'Retail',
                'name_ar'        => 'التجزئة',
                'slug'           => 'retail',
                'icon'           => 'heroicon-o-shopping-bag',
                'description'    => 'Templates and layouts designed for retail stores including clothing, electronics, and general merchandise.',
                'description_ar' => 'قوالب وتخطيطات مصممة لمتاجر التجزئة بما في ذلك الملابس والإلكترونيات والبضائع العامة.',
                'sort_order'     => 1,
                'is_active'      => true,
            ],
            [
                'name'           => 'Restaurant',
                'name_ar'        => 'المطاعم',
                'slug'           => 'restaurant',
                'icon'           => 'heroicon-o-cake',
                'description'    => 'Layouts optimized for restaurants, cafés, and food service businesses with table management.',
                'description_ar' => 'تخطيطات محسّنة للمطاعم والمقاهي وشركات الخدمات الغذائية مع إدارة الطاولات.',
                'sort_order'     => 2,
                'is_active'      => true,
            ],
            [
                'name'           => 'Grocery',
                'name_ar'        => 'البقالة',
                'slug'           => 'grocery',
                'icon'           => 'heroicon-o-shopping-cart',
                'description'    => 'Templates for grocery stores and supermarkets with scale integration and produce handling.',
                'description_ar' => 'قوالب لمحلات البقالة والسوبرماركت مع تكامل الميزان ومعالجة المنتجات.',
                'sort_order'     => 3,
                'is_active'      => true,
            ],
            [
                'name'           => 'Pharmacy',
                'name_ar'        => 'الصيدلية',
                'slug'           => 'pharmacy',
                'icon'           => 'heroicon-o-heart',
                'description'    => 'Pharmacy-specific layouts with prescription tracking, drug schedules, and patient management.',
                'description_ar' => 'تخطيطات خاصة بالصيدلية مع تتبع الوصفات وجداول الأدوية وإدارة المرضى.',
                'sort_order'     => 4,
                'is_active'      => true,
            ],
            [
                'name'           => 'Electronics',
                'name_ar'        => 'الإلكترونيات',
                'slug'           => 'electronics',
                'icon'           => 'heroicon-o-device-phone-mobile',
                'description'    => 'Templates for electronics and mobile phone shops with IMEI tracking and warranty management.',
                'description_ar' => 'قوالب لمحلات الإلكترونيات والهواتف المحمولة مع تتبع IMEI وإدارة الضمان.',
                'sort_order'     => 5,
                'is_active'      => true,
            ],
            [
                'name'           => 'Fashion',
                'name_ar'        => 'الأزياء',
                'slug'           => 'fashion',
                'icon'           => 'heroicon-o-sparkles',
                'description'    => 'Fashion and apparel templates with size, color variants, and visual merchandising layouts.',
                'description_ar' => 'قوالب الأزياء والملابس مع المقاسات والألوان والتخطيطات المرئية للبضائع.',
                'sort_order'     => 6,
                'is_active'      => true,
            ],
            [
                'name'           => 'Services',
                'name_ar'        => 'الخدمات',
                'slug'           => 'services',
                'icon'           => 'heroicon-o-wrench-screwdriver',
                'description'    => 'Layouts for service-based businesses like salons, repair shops, and consulting firms.',
                'description_ar' => 'تخطيطات للأعمال القائمة على الخدمات مثل الصالونات ومحلات الإصلاح والشركات الاستشارية.',
                'sort_order'     => 7,
                'is_active'      => true,
            ],
            [
                'name'           => 'Minimal',
                'name_ar'        => 'بسيط',
                'slug'           => 'minimal',
                'icon'           => 'heroicon-o-minus',
                'description'    => 'Clean, minimal templates that work for any business type with essential features only.',
                'description_ar' => 'قوالب نظيفة وبسيطة تعمل مع أي نوع عمل مع الميزات الأساسية فقط.',
                'sort_order'     => 8,
                'is_active'      => true,
            ],
            // ── NEW CATEGORIES ──
            [
                'name'           => 'Premium',
                'name_ar'        => 'متميز',
                'slug'           => 'premium',
                'icon'           => 'heroicon-o-trophy',
                'description'    => 'Premium, professionally designed templates with advanced features, animations, and branding options.',
                'description_ar' => 'قوالب متميزة مصممة باحترافية مع ميزات متقدمة ورسوم متحركة وخيارات العلامة التجارية.',
                'sort_order'     => 9,
                'is_active'      => true,
            ],
            [
                'name'           => 'Seasonal',
                'name_ar'        => 'موسمي',
                'slug'           => 'seasonal',
                'icon'           => 'heroicon-o-calendar-days',
                'description'    => 'Seasonal and holiday-themed templates for Ramadan, Eid, National Day, and other occasions.',
                'description_ar' => 'قوالب موسمية وعطلات لشهر رمضان والعيد واليوم الوطني والمناسبات الأخرى.',
                'sort_order'     => 10,
                'is_active'      => true,
            ],
        ];

        foreach ($categories as $data) {
            MarketplaceCategory::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
