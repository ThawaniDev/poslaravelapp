<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds 5 predefined categories and 10 predefined products
 * for each business type (grocery, restaurant, pharmacy,
 * bakery, electronics, florist, jewelry, fashion).
 *
 * Stores can clone these into their own catalog via the
 * predefined catalog page in the provider app.
 */
class PredefinedCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = $this->catalog();

        foreach ($catalog as $slug => $data) {
            $businessTypeId = DB::table('business_types')->where('slug', $slug)->value('id');

            if (! $businessTypeId) {
                $this->command?->warn("  ⚠ business_type slug '{$slug}' not found — skipping");
                continue;
            }

            $categoryIdByName = [];

            foreach ($data['categories'] as $sortOrder => $cat) {
                $existingId = DB::table('predefined_categories')
                    ->where('business_type_id', $businessTypeId)
                    ->where('name', $cat['name'])
                    ->value('id');

                if ($existingId) {
                    $categoryIdByName[$cat['name']] = $existingId;
                    continue;
                }

                $id = (string) Str::uuid();
                DB::table('predefined_categories')->insert([
                    'id'               => $id,
                    'business_type_id' => $businessTypeId,
                    'parent_id'        => null,
                    'name'             => $cat['name'],
                    'name_ar'          => $cat['name_ar'],
                    'description'      => $cat['description'] ?? null,
                    'description_ar'   => $cat['description_ar'] ?? null,
                    'image_url'        => null,
                    'sort_order'       => $sortOrder + 1,
                    'is_active'        => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $categoryIdByName[$cat['name']] = $id;
            }

            foreach ($data['products'] as $product) {
                $existing = DB::table('predefined_products')
                    ->where('business_type_id', $businessTypeId)
                    ->where('name', $product['name'])
                    ->exists();

                if ($existing) {
                    continue;
                }

                DB::table('predefined_products')->insert([
                    'id'                     => (string) Str::uuid(),
                    'business_type_id'       => $businessTypeId,
                    'predefined_category_id' => $categoryIdByName[$product['category']] ?? null,
                    'name'                   => $product['name'],
                    'name_ar'                => $product['name_ar'],
                    'description'            => $product['description'] ?? null,
                    'description_ar'         => $product['description_ar'] ?? null,
                    'sku'                    => $product['sku'] ?? null,
                    'barcode'                => $product['barcode'] ?? null,
                    'sell_price'             => $product['sell_price'],
                    'cost_price'             => $product['cost_price'] ?? null,
                    'unit'                   => $product['unit'] ?? 'piece',
                    'tax_rate'               => $product['tax_rate'] ?? 15.00,
                    'is_weighable'           => $product['is_weighable'] ?? false,
                    'tare_weight'            => 0,
                    'is_active'              => true,
                    'age_restricted'         => $product['age_restricted'] ?? false,
                    'image_url'              => null,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            }

            $this->command?->info("  ✓ {$slug}: " . count($data['categories']) . ' categories, ' . count($data['products']) . ' products');
        }
    }

    private function catalog(): array
    {
        return [
            // ─── Grocery ─────────────────────────────────────────
            'grocery' => [
                'categories' => [
                    ['name' => 'Fruits & Vegetables', 'name_ar' => 'فواكه وخضروات'],
                    ['name' => 'Dairy & Eggs',        'name_ar' => 'ألبان وبيض'],
                    ['name' => 'Beverages',           'name_ar' => 'مشروبات'],
                    ['name' => 'Snacks',              'name_ar' => 'وجبات خفيفة'],
                    ['name' => 'Cleaning Supplies',   'name_ar' => 'مستلزمات التنظيف'],
                ],
                'products' => [
                    ['category' => 'Fruits & Vegetables', 'name' => 'Banana',          'name_ar' => 'موز',          'sell_price' => 6.00,  'cost_price' => 3.50,  'unit' => 'kg', 'is_weighable' => true,  'sku' => 'GRC-BAN-001'],
                    ['category' => 'Fruits & Vegetables', 'name' => 'Tomato',          'name_ar' => 'طماطم',        'sell_price' => 5.50,  'cost_price' => 2.80,  'unit' => 'kg', 'is_weighable' => true,  'sku' => 'GRC-TOM-001'],
                    ['category' => 'Fruits & Vegetables', 'name' => 'Cucumber',        'name_ar' => 'خيار',         'sell_price' => 4.00,  'cost_price' => 2.00,  'unit' => 'kg', 'is_weighable' => true,  'sku' => 'GRC-CUC-001'],
                    ['category' => 'Dairy & Eggs',        'name' => 'Fresh Milk 1L',   'name_ar' => 'حليب طازج 1 لتر', 'sell_price' => 8.00, 'cost_price' => 5.50, 'unit' => 'piece', 'sku' => 'GRC-MLK-001'],
                    ['category' => 'Dairy & Eggs',        'name' => 'Eggs (Tray 30)',  'name_ar' => 'بيض (طبق 30)', 'sell_price' => 22.00, 'cost_price' => 16.00, 'unit' => 'piece', 'sku' => 'GRC-EGG-001'],
                    ['category' => 'Beverages',           'name' => 'Bottled Water 500ml', 'name_ar' => 'مياه 500 مل', 'sell_price' => 1.50, 'cost_price' => 0.60, 'unit' => 'piece', 'sku' => 'GRC-WTR-001'],
                    ['category' => 'Beverages',           'name' => 'Cola Can 330ml',  'name_ar' => 'كولا علبة 330 مل', 'sell_price' => 3.00, 'cost_price' => 1.50, 'unit' => 'piece', 'sku' => 'GRC-COL-001'],
                    ['category' => 'Snacks',              'name' => 'Potato Chips',    'name_ar' => 'رقائق البطاطس', 'sell_price' => 4.50, 'cost_price' => 2.20, 'unit' => 'piece', 'sku' => 'GRC-CHP-001'],
                    ['category' => 'Snacks',              'name' => 'Chocolate Bar',   'name_ar' => 'لوح شوكولاتة', 'sell_price' => 5.00, 'cost_price' => 2.50, 'unit' => 'piece', 'sku' => 'GRC-CHC-001'],
                    ['category' => 'Cleaning Supplies',   'name' => 'Dish Soap 500ml', 'name_ar' => 'سائل غسيل صحون 500 مل', 'sell_price' => 9.00, 'cost_price' => 5.00, 'unit' => 'piece', 'sku' => 'GRC-DSH-001'],
                ],
            ],

            // ─── Restaurant ──────────────────────────────────────
            'restaurant' => [
                'categories' => [
                    ['name' => 'Appetizers',   'name_ar' => 'مقبلات'],
                    ['name' => 'Main Courses', 'name_ar' => 'الأطباق الرئيسية'],
                    ['name' => 'Drinks',       'name_ar' => 'مشروبات'],
                    ['name' => 'Desserts',     'name_ar' => 'حلويات'],
                    ['name' => 'Sides',        'name_ar' => 'إضافات'],
                ],
                'products' => [
                    ['category' => 'Appetizers',   'name' => 'Hummus',           'name_ar' => 'حمص',          'sell_price' => 18.00, 'cost_price' => 6.00,  'unit' => 'plate'],
                    ['category' => 'Appetizers',   'name' => 'Fattoush Salad',   'name_ar' => 'فتوش',         'sell_price' => 22.00, 'cost_price' => 8.00,  'unit' => 'plate'],
                    ['category' => 'Main Courses', 'name' => 'Mixed Grill',      'name_ar' => 'مشاوي مشكلة', 'sell_price' => 75.00, 'cost_price' => 30.00, 'unit' => 'plate'],
                    ['category' => 'Main Courses', 'name' => 'Chicken Shawarma', 'name_ar' => 'شاورما دجاج', 'sell_price' => 25.00, 'cost_price' => 10.00, 'unit' => 'piece'],
                    ['category' => 'Main Courses', 'name' => 'Beef Burger',      'name_ar' => 'برجر لحم',     'sell_price' => 35.00, 'cost_price' => 14.00, 'unit' => 'piece'],
                    ['category' => 'Drinks',       'name' => 'Fresh Lemonade',   'name_ar' => 'ليموناضة',     'sell_price' => 12.00, 'cost_price' => 3.00,  'unit' => 'glass'],
                    ['category' => 'Drinks',       'name' => 'Arabic Coffee',    'name_ar' => 'قهوة عربية',  'sell_price' => 10.00, 'cost_price' => 2.00,  'unit' => 'cup'],
                    ['category' => 'Desserts',     'name' => 'Kunafa',           'name_ar' => 'كنافة',        'sell_price' => 28.00, 'cost_price' => 9.00,  'unit' => 'plate'],
                    ['category' => 'Desserts',     'name' => 'Umm Ali',          'name_ar' => 'أم علي',       'sell_price' => 24.00, 'cost_price' => 7.00,  'unit' => 'bowl'],
                    ['category' => 'Sides',        'name' => 'French Fries',     'name_ar' => 'بطاطس مقلية', 'sell_price' => 12.00, 'cost_price' => 4.00,  'unit' => 'plate'],
                ],
            ],

            // ─── Pharmacy ────────────────────────────────────────
            'pharmacy' => [
                'categories' => [
                    ['name' => 'Pain Relief',     'name_ar' => 'مسكنات الألم'],
                    ['name' => 'Cold & Flu',      'name_ar' => 'البرد والإنفلونزا'],
                    ['name' => 'Vitamins',        'name_ar' => 'فيتامينات'],
                    ['name' => 'Personal Care',   'name_ar' => 'العناية الشخصية'],
                    ['name' => 'Baby Care',       'name_ar' => 'العناية بالأطفال'],
                ],
                'products' => [
                    ['category' => 'Pain Relief',   'name' => 'Paracetamol 500mg (20 tab)', 'name_ar' => 'باراسيتامول 500 ملغ', 'sell_price' => 12.00, 'cost_price' => 5.00,  'unit' => 'box', 'tax_rate' => 0, 'sku' => 'PHM-PAR-001'],
                    ['category' => 'Pain Relief',   'name' => 'Ibuprofen 400mg (20 tab)',   'name_ar' => 'إيبوبروفين 400 ملغ', 'sell_price' => 18.00, 'cost_price' => 8.00,  'unit' => 'box', 'tax_rate' => 0, 'sku' => 'PHM-IBU-001'],
                    ['category' => 'Cold & Flu',    'name' => 'Cough Syrup 100ml',          'name_ar' => 'شراب سعال 100 مل', 'sell_price' => 25.00, 'cost_price' => 12.00, 'unit' => 'bottle', 'tax_rate' => 0, 'sku' => 'PHM-CGH-001'],
                    ['category' => 'Cold & Flu',    'name' => 'Throat Lozenges',            'name_ar' => 'حبوب استحلاب للحلق', 'sell_price' => 14.00, 'cost_price' => 6.00,  'unit' => 'pack', 'tax_rate' => 0, 'sku' => 'PHM-LOZ-001'],
                    ['category' => 'Vitamins',      'name' => 'Vitamin C 1000mg',           'name_ar' => 'فيتامين سي 1000 ملغ', 'sell_price' => 35.00, 'cost_price' => 18.00, 'unit' => 'bottle', 'sku' => 'PHM-VTC-001'],
                    ['category' => 'Vitamins',      'name' => 'Multivitamin Daily',         'name_ar' => 'فيتامينات يومية متعددة', 'sell_price' => 55.00, 'cost_price' => 28.00, 'unit' => 'bottle', 'sku' => 'PHM-MLT-001'],
                    ['category' => 'Personal Care', 'name' => 'Hand Sanitizer 250ml',       'name_ar' => 'معقم أيدي 250 مل', 'sell_price' => 15.00, 'cost_price' => 7.00,  'unit' => 'bottle', 'sku' => 'PHM-SAN-001'],
                    ['category' => 'Personal Care', 'name' => 'Antiseptic Cream',           'name_ar' => 'كريم مطهر',     'sell_price' => 22.00, 'cost_price' => 10.00, 'unit' => 'tube', 'sku' => 'PHM-ANT-001'],
                    ['category' => 'Baby Care',     'name' => 'Baby Diapers Medium (40)',   'name_ar' => 'حفاضات أطفال مقاس متوسط', 'sell_price' => 65.00, 'cost_price' => 38.00, 'unit' => 'pack', 'sku' => 'PHM-DIA-001'],
                    ['category' => 'Baby Care',     'name' => 'Baby Wipes (80)',            'name_ar' => 'مناديل أطفال (80)', 'sell_price' => 18.00, 'cost_price' => 9.00,  'unit' => 'pack', 'sku' => 'PHM-WIP-001'],
                ],
            ],

            // ─── Bakery ──────────────────────────────────────────
            'bakery' => [
                'categories' => [
                    ['name' => 'Breads',     'name_ar' => 'خبز'],
                    ['name' => 'Cakes',      'name_ar' => 'كيك'],
                    ['name' => 'Pastries',   'name_ar' => 'معجنات'],
                    ['name' => 'Cookies',    'name_ar' => 'بسكويت'],
                    ['name' => 'Beverages',  'name_ar' => 'مشروبات'],
                ],
                'products' => [
                    ['category' => 'Breads',    'name' => 'Arabic Bread',     'name_ar' => 'خبز عربي',      'sell_price' => 4.00,  'cost_price' => 1.50, 'unit' => 'pack'],
                    ['category' => 'Breads',    'name' => 'Sourdough Loaf',   'name_ar' => 'خبز العجين المخمر', 'sell_price' => 18.00, 'cost_price' => 6.00, 'unit' => 'piece'],
                    ['category' => 'Breads',    'name' => 'Croissant',        'name_ar' => 'كرواسون',       'sell_price' => 8.00,  'cost_price' => 2.50, 'unit' => 'piece'],
                    ['category' => 'Cakes',     'name' => 'Chocolate Cake Slice', 'name_ar' => 'شريحة كيك الشوكولاتة', 'sell_price' => 22.00, 'cost_price' => 7.00, 'unit' => 'slice'],
                    ['category' => 'Cakes',     'name' => 'Cheesecake Slice', 'name_ar' => 'شريحة تشيز كيك', 'sell_price' => 25.00, 'cost_price' => 8.00, 'unit' => 'slice'],
                    ['category' => 'Pastries',  'name' => 'Cheese Manakeesh', 'name_ar' => 'مناقيش جبنة',   'sell_price' => 10.00, 'cost_price' => 3.50, 'unit' => 'piece'],
                    ['category' => 'Pastries',  'name' => 'Zaatar Manakeesh', 'name_ar' => 'مناقيش زعتر',   'sell_price' => 8.00,  'cost_price' => 2.50, 'unit' => 'piece'],
                    ['category' => 'Cookies',   'name' => 'Maamoul (Box of 12)', 'name_ar' => 'معمول (علبة 12)', 'sell_price' => 45.00, 'cost_price' => 20.00, 'unit' => 'box'],
                    ['category' => 'Cookies',   'name' => 'Chocolate Chip Cookie', 'name_ar' => 'كوكيز شوكولاتة', 'sell_price' => 6.00, 'cost_price' => 2.00, 'unit' => 'piece'],
                    ['category' => 'Beverages', 'name' => 'Cappuccino',       'name_ar' => 'كابتشينو',      'sell_price' => 14.00, 'cost_price' => 4.00, 'unit' => 'cup'],
                ],
            ],

            // ─── Electronics ─────────────────────────────────────
            'electronics' => [
                'categories' => [
                    ['name' => 'Mobile Phones',    'name_ar' => 'الهواتف المحمولة'],
                    ['name' => 'Accessories',      'name_ar' => 'إكسسوارات'],
                    ['name' => 'Audio',            'name_ar' => 'صوتيات'],
                    ['name' => 'Computers',        'name_ar' => 'حواسيب'],
                    ['name' => 'Smart Home',       'name_ar' => 'المنزل الذكي'],
                ],
                'products' => [
                    ['category' => 'Mobile Phones', 'name' => 'Smartphone Mid-Range',  'name_ar' => 'هاتف ذكي متوسط الفئة', 'sell_price' => 1499.00, 'cost_price' => 1100.00, 'unit' => 'piece', 'sku' => 'ELC-PHN-001'],
                    ['category' => 'Mobile Phones', 'name' => 'Feature Phone',         'name_ar' => 'هاتف بسيط',           'sell_price' => 199.00,  'cost_price' => 130.00,  'unit' => 'piece', 'sku' => 'ELC-PHN-002'],
                    ['category' => 'Accessories',   'name' => 'USB-C Cable 1m',        'name_ar' => 'كابل USB-C 1 متر',    'sell_price' => 25.00,   'cost_price' => 8.00,    'unit' => 'piece', 'sku' => 'ELC-CBL-001'],
                    ['category' => 'Accessories',   'name' => 'Phone Case Universal',  'name_ar' => 'جراب هاتف عام',       'sell_price' => 35.00,   'cost_price' => 12.00,   'unit' => 'piece', 'sku' => 'ELC-CSE-001'],
                    ['category' => 'Accessories',   'name' => 'Screen Protector',      'name_ar' => 'لاصقة حماية الشاشة',  'sell_price' => 20.00,   'cost_price' => 5.00,    'unit' => 'piece', 'sku' => 'ELC-SCR-001'],
                    ['category' => 'Audio',         'name' => 'Wireless Earbuds',      'name_ar' => 'سماعات لاسلكية',      'sell_price' => 199.00,  'cost_price' => 95.00,   'unit' => 'piece', 'sku' => 'ELC-EAR-001'],
                    ['category' => 'Audio',         'name' => 'Bluetooth Speaker',     'name_ar' => 'مكبر صوت بلوتوث',     'sell_price' => 149.00,  'cost_price' => 70.00,   'unit' => 'piece', 'sku' => 'ELC-SPK-001'],
                    ['category' => 'Computers',     'name' => 'Wireless Mouse',        'name_ar' => 'فأرة لاسلكية',        'sell_price' => 55.00,   'cost_price' => 22.00,   'unit' => 'piece', 'sku' => 'ELC-MSE-001'],
                    ['category' => 'Computers',     'name' => 'USB Flash Drive 32GB',  'name_ar' => 'فلاش USB 32 جيجا',    'sell_price' => 30.00,   'cost_price' => 12.00,   'unit' => 'piece', 'sku' => 'ELC-USB-001'],
                    ['category' => 'Smart Home',    'name' => 'Smart LED Bulb',        'name_ar' => 'مصباح LED ذكي',       'sell_price' => 75.00,   'cost_price' => 30.00,   'unit' => 'piece', 'sku' => 'ELC-BLB-001'],
                ],
            ],

            // ─── Florist ─────────────────────────────────────────
            'florist' => [
                'categories' => [
                    ['name' => 'Bouquets',        'name_ar' => 'باقات'],
                    ['name' => 'Single Stems',    'name_ar' => 'زهور مفردة'],
                    ['name' => 'Plants',          'name_ar' => 'نباتات'],
                    ['name' => 'Arrangements',    'name_ar' => 'تنسيقات'],
                    ['name' => 'Gift Add-ons',    'name_ar' => 'إضافات هدية'],
                ],
                'products' => [
                    ['category' => 'Bouquets',     'name' => 'Red Rose Bouquet (12)', 'name_ar' => 'باقة ورد أحمر (12)', 'sell_price' => 180.00, 'cost_price' => 80.00, 'unit' => 'bouquet'],
                    ['category' => 'Bouquets',     'name' => 'Mixed Flowers Bouquet', 'name_ar' => 'باقة زهور مشكلة', 'sell_price' => 220.00, 'cost_price' => 95.00, 'unit' => 'bouquet'],
                    ['category' => 'Bouquets',     'name' => 'White Lily Bouquet',    'name_ar' => 'باقة زنبق أبيض', 'sell_price' => 250.00, 'cost_price' => 110.00, 'unit' => 'bouquet'],
                    ['category' => 'Single Stems', 'name' => 'Single Red Rose',       'name_ar' => 'وردة حمراء مفردة', 'sell_price' => 18.00,  'cost_price' => 6.00,  'unit' => 'stem'],
                    ['category' => 'Single Stems', 'name' => 'Sunflower Stem',        'name_ar' => 'عباد الشمس',     'sell_price' => 25.00,  'cost_price' => 9.00,  'unit' => 'stem'],
                    ['category' => 'Plants',       'name' => 'Succulent Pot Small',   'name_ar' => 'نبتة عصارية صغيرة', 'sell_price' => 65.00, 'cost_price' => 25.00, 'unit' => 'piece'],
                    ['category' => 'Plants',       'name' => 'Orchid in Pot',         'name_ar' => 'أوركيد في أصيص', 'sell_price' => 195.00, 'cost_price' => 90.00, 'unit' => 'piece'],
                    ['category' => 'Arrangements', 'name' => 'Table Centerpiece',     'name_ar' => 'تنسيق طاولة',    'sell_price' => 350.00, 'cost_price' => 150.00, 'unit' => 'piece'],
                    ['category' => 'Gift Add-ons', 'name' => 'Greeting Card',         'name_ar' => 'بطاقة معايدة',   'sell_price' => 15.00,  'cost_price' => 4.00,  'unit' => 'piece'],
                    ['category' => 'Gift Add-ons', 'name' => 'Box of Chocolates',     'name_ar' => 'علبة شوكولاتة',  'sell_price' => 85.00,  'cost_price' => 38.00, 'unit' => 'box'],
                ],
            ],

            // ─── Jewelry ─────────────────────────────────────────
            'jewelry' => [
                'categories' => [
                    ['name' => 'Rings',     'name_ar' => 'خواتم'],
                    ['name' => 'Necklaces', 'name_ar' => 'قلائد'],
                    ['name' => 'Bracelets', 'name_ar' => 'أساور'],
                    ['name' => 'Earrings',  'name_ar' => 'أقراط'],
                    ['name' => 'Watches',   'name_ar' => 'ساعات'],
                ],
                'products' => [
                    ['category' => 'Rings',     'name' => 'Gold Wedding Ring 18K',  'name_ar' => 'خاتم زفاف ذهب 18 قيراط', 'sell_price' => 1850.00, 'cost_price' => 1500.00, 'unit' => 'piece', 'sku' => 'JWL-RNG-001'],
                    ['category' => 'Rings',     'name' => 'Silver Engagement Ring', 'name_ar' => 'خاتم خطوبة فضة',         'sell_price' => 450.00,  'cost_price' => 280.00,  'unit' => 'piece', 'sku' => 'JWL-RNG-002'],
                    ['category' => 'Necklaces', 'name' => 'Gold Chain 18K 50cm',    'name_ar' => 'سلسلة ذهب 18 قيراط 50 سم', 'sell_price' => 2400.00, 'cost_price' => 1900.00, 'unit' => 'piece', 'sku' => 'JWL-NCK-001'],
                    ['category' => 'Necklaces', 'name' => 'Pearl Necklace',         'name_ar' => 'قلادة لؤلؤ',             'sell_price' => 950.00,  'cost_price' => 600.00,  'unit' => 'piece', 'sku' => 'JWL-NCK-002'],
                    ['category' => 'Bracelets', 'name' => 'Gold Bangle 22K',        'name_ar' => 'إسورة ذهب 22 قيراط',     'sell_price' => 3200.00, 'cost_price' => 2700.00, 'unit' => 'piece', 'sku' => 'JWL-BRC-001'],
                    ['category' => 'Bracelets', 'name' => 'Silver Charm Bracelet',  'name_ar' => 'إسورة فضة بحلقات',       'sell_price' => 380.00,  'cost_price' => 220.00,  'unit' => 'piece', 'sku' => 'JWL-BRC-002'],
                    ['category' => 'Earrings',  'name' => 'Diamond Stud Earrings',  'name_ar' => 'أقراط ألماس',            'sell_price' => 2750.00, 'cost_price' => 2100.00, 'unit' => 'pair',  'sku' => 'JWL-EAR-001'],
                    ['category' => 'Earrings',  'name' => 'Gold Hoops 18K',         'name_ar' => 'أقراط حلقية ذهب 18 قيراط', 'sell_price' => 850.00, 'cost_price' => 620.00, 'unit' => 'pair', 'sku' => 'JWL-EAR-002'],
                    ['category' => 'Watches',   'name' => 'Luxury Mens Watch',      'name_ar' => 'ساعة رجالية فاخرة',      'sell_price' => 4500.00, 'cost_price' => 3200.00, 'unit' => 'piece', 'sku' => 'JWL-WCH-001'],
                    ['category' => 'Watches',   'name' => 'Ladies Dress Watch',     'name_ar' => 'ساعة نسائية أنيقة',       'sell_price' => 1200.00, 'cost_price' => 800.00,  'unit' => 'piece', 'sku' => 'JWL-WCH-002'],
                ],
            ],

            // ─── Fashion ─────────────────────────────────────────
            'fashion' => [
                'categories' => [
                    ['name' => 'Mens Clothing',     'name_ar' => 'ملابس رجالية'],
                    ['name' => 'Womens Clothing',   'name_ar' => 'ملابس نسائية'],
                    ['name' => 'Footwear',          'name_ar' => 'أحذية'],
                    ['name' => 'Bags & Accessories','name_ar' => 'حقائب وإكسسوارات'],
                    ['name' => 'Kids Clothing',     'name_ar' => 'ملابس أطفال'],
                ],
                'products' => [
                    ['category' => 'Mens Clothing',      'name' => 'Mens Cotton T-Shirt',     'name_ar' => 'تيشيرت قطن رجالي',      'sell_price' => 65.00,  'cost_price' => 25.00,  'unit' => 'piece', 'sku' => 'FSH-MTS-001'],
                    ['category' => 'Mens Clothing',      'name' => 'Mens Slim Jeans',         'name_ar' => 'جينز رجالي ضيق',         'sell_price' => 195.00, 'cost_price' => 85.00,  'unit' => 'piece', 'sku' => 'FSH-MJN-001'],
                    ['category' => 'Womens Clothing',    'name' => 'Womens Abaya',            'name_ar' => 'عباية نسائية',           'sell_price' => 350.00, 'cost_price' => 160.00, 'unit' => 'piece', 'sku' => 'FSH-WAB-001'],
                    ['category' => 'Womens Clothing',    'name' => 'Womens Blouse',           'name_ar' => 'بلوزة نسائية',           'sell_price' => 145.00, 'cost_price' => 60.00,  'unit' => 'piece', 'sku' => 'FSH-WBL-001'],
                    ['category' => 'Footwear',           'name' => 'Mens Sneakers',           'name_ar' => 'حذاء رياضي رجالي',       'sell_price' => 245.00, 'cost_price' => 110.00, 'unit' => 'pair',  'sku' => 'FSH-MSN-001'],
                    ['category' => 'Footwear',           'name' => 'Womens Heels',            'name_ar' => 'كعب نسائي',              'sell_price' => 185.00, 'cost_price' => 75.00,  'unit' => 'pair',  'sku' => 'FSH-WHL-001'],
                    ['category' => 'Bags & Accessories', 'name' => 'Leather Handbag',         'name_ar' => 'حقيبة يد جلدية',         'sell_price' => 425.00, 'cost_price' => 180.00, 'unit' => 'piece', 'sku' => 'FSH-BAG-001'],
                    ['category' => 'Bags & Accessories', 'name' => 'Leather Belt',            'name_ar' => 'حزام جلدي',              'sell_price' => 95.00,  'cost_price' => 35.00,  'unit' => 'piece', 'sku' => 'FSH-BLT-001'],
                    ['category' => 'Kids Clothing',      'name' => 'Kids T-Shirt',            'name_ar' => 'تيشيرت أطفال',           'sell_price' => 45.00,  'cost_price' => 18.00,  'unit' => 'piece', 'sku' => 'FSH-KTS-001'],
                    ['category' => 'Kids Clothing',      'name' => 'Kids Jeans',              'name_ar' => 'جينز أطفال',             'sell_price' => 95.00,  'cost_price' => 38.00,  'unit' => 'piece', 'sku' => 'FSH-KJN-001'],
                ],
            ],
        ];
    }
}
