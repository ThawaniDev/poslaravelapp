<?php

namespace Database\Seeders;

use App\Domain\ProviderRegistration\Models\BusinessTypeTemplate;
use Illuminate\Database\Seeder;

class BusinessTypeTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'retail',
                'name_en' => 'Retail Store',
                'name_ar' => 'متجر تجزئة',
                'description_en' => 'General retail store for selling physical goods.',
                'description_ar' => 'متجر تجزئة عام لبيع السلع المادية.',
                'icon' => 'store',
                'display_order' => 1,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => false,
                    'enable_tips' => false,
                    'require_customer_for_sale' => false,
                    'suggested_categories' => ['Electronics', 'Clothing', 'Accessories', 'Home', 'Beauty'],
                ],
            ],
            [
                'code' => 'restaurant',
                'name_en' => 'Restaurant / Café',
                'name_ar' => 'مطعم / مقهى',
                'description_en' => 'Dine-in, takeaway, or delivery food service.',
                'description_ar' => 'خدمة طعام داخلية أو خارجية أو توصيل.',
                'icon' => 'restaurant',
                'display_order' => 2,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => true,
                    'enable_tips' => true,
                    'require_customer_for_sale' => false,
                    'suggested_categories' => ['Appetizers', 'Main Courses', 'Drinks', 'Desserts', 'Sides'],
                ],
            ],
            [
                'code' => 'pharmacy',
                'name_en' => 'Pharmacy',
                'name_ar' => 'صيدلية',
                'description_en' => 'Pharmaceutical products and health supplies.',
                'description_ar' => 'منتجات صيدلانية ومستلزمات صحية.',
                'icon' => 'local_pharmacy',
                'display_order' => 3,
                'template_json' => [
                    'tax_rate' => 0.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => false,
                    'enable_tips' => false,
                    'require_customer_for_sale' => true,
                    'track_batch_numbers' => true,
                    'track_expiry_dates' => true,
                    'suggested_categories' => ['Prescription', 'OTC Medication', 'Personal Care', 'Medical Devices', 'Supplements'],
                ],
            ],
            [
                'code' => 'grocery',
                'name_en' => 'Grocery / Supermarket',
                'name_ar' => 'بقالة / سوبرماركت',
                'description_en' => 'Food and daily necessities.',
                'description_ar' => 'مواد غذائية ومستلزمات يومية.',
                'icon' => 'shopping_cart',
                'display_order' => 4,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => false,
                    'enable_tips' => false,
                    'require_customer_for_sale' => false,
                    'track_expiry_dates' => true,
                    'suggested_categories' => ['Fruits & Vegetables', 'Dairy', 'Meat & Fish', 'Beverages', 'Snacks', 'Household'],
                ],
            ],
            [
                'code' => 'jewelry',
                'name_en' => 'Jewelry Store',
                'name_ar' => 'محل مجوهرات',
                'description_en' => 'Jewelry, gold, and precious metals.',
                'description_ar' => 'مجوهرات وذهب ومعادن ثمينة.',
                'icon' => 'diamond',
                'display_order' => 5,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => false,
                    'enable_tips' => false,
                    'require_customer_for_sale' => true,
                    'track_serial_numbers' => true,
                    'suggested_categories' => ['Rings', 'Necklaces', 'Bracelets', 'Watches', 'Earrings'],
                ],
            ],
            [
                'code' => 'mobile_shop',
                'name_en' => 'Mobile & Electronics',
                'name_ar' => 'جوالات وإلكترونيات',
                'description_en' => 'Mobile phones, electronics, and accessories.',
                'description_ar' => 'جوالات وإلكترونيات وإكسسوارات.',
                'icon' => 'smartphone',
                'display_order' => 6,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => false,
                    'enable_tips' => false,
                    'require_customer_for_sale' => false,
                    'track_serial_numbers' => true,
                    'track_imei' => true,
                    'suggested_categories' => ['Smartphones', 'Tablets', 'Accessories', 'Repairs', 'SIM Cards'],
                ],
            ],
            [
                'code' => 'flower_shop',
                'name_en' => 'Flower Shop',
                'name_ar' => 'محل زهور',
                'description_en' => 'Fresh flowers, arrangements, and gifts.',
                'description_ar' => 'زهور طازجة وتنسيقات وهدايا.',
                'icon' => 'local_florist',
                'display_order' => 7,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => false,
                    'enable_tips' => false,
                    'require_customer_for_sale' => false,
                    'suggested_categories' => ['Bouquets', 'Arrangements', 'Plants', 'Gift Sets', 'Occasions'],
                ],
            ],
            [
                'code' => 'bakery',
                'name_en' => 'Bakery & Pastry',
                'name_ar' => 'مخبز وحلويات',
                'description_en' => 'Fresh baked goods and pastries.',
                'description_ar' => 'مخبوزات طازجة وحلويات.',
                'icon' => 'bakery_dining',
                'display_order' => 8,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => true,
                    'enable_tips' => false,
                    'require_customer_for_sale' => false,
                    'suggested_categories' => ['Bread', 'Pastries', 'Cakes', 'Cookies', 'Drinks'],
                ],
            ],
            [
                'code' => 'service',
                'name_en' => 'Service Business',
                'name_ar' => 'أعمال خدمية',
                'description_en' => 'Service-based business (salon, laundry, etc.).',
                'description_ar' => 'أعمال قائمة على الخدمات (صالون، غسيل، إلخ).',
                'icon' => 'room_service',
                'display_order' => 9,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => false,
                    'enable_tips' => true,
                    'require_customer_for_sale' => true,
                    'suggested_categories' => ['Haircuts', 'Coloring', 'Treatments', 'Products', 'Packages'],
                ],
            ],
            [
                'code' => 'custom',
                'name_en' => 'Custom Business',
                'name_ar' => 'نشاط مخصص',
                'description_en' => 'Fully customizable setup for any business type.',
                'description_ar' => 'إعداد قابل للتخصيص بالكامل لأي نوع نشاط.',
                'icon' => 'tune',
                'display_order' => 10,
                'template_json' => [
                    'tax_rate' => 15.0,
                    'prices_include_tax' => true,
                    'enable_kitchen_display' => false,
                    'enable_tips' => false,
                    'require_customer_for_sale' => false,
                ],
            ],
        ];

        foreach ($templates as $data) {
            BusinessTypeTemplate::updateOrCreate(
                ['code' => $data['code']],
                $data,
            );
        }
    }
}
