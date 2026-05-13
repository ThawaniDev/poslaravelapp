<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Models\PricingPageContent;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed 3 subscription plans: Starter, Professional, Enterprise.
     * Prices: 99 SAR / 299 SAR / 699 SAR per month.
     */
    public function run(): void
    {
        $plans = $this->createPlans();

        foreach ($plans as $planData) {
            $features = $planData['features'];
            $limits   = $planData['limits'];
            unset($planData['features'], $planData['limits']);

            $plan = SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData,
            );

            // Sync feature toggles
            PlanFeatureToggle::where('subscription_plan_id', $plan->id)->delete();
            foreach ($features as $feature) {
                PlanFeatureToggle::create(['subscription_plan_id' => $plan->id, ...$feature]);
            }

            // Sync limits
            PlanLimit::where('subscription_plan_id', $plan->id)->delete();
            foreach ($limits as $limit) {
                PlanLimit::create(['subscription_plan_id' => $plan->id, ...$limit]);
            }
        }

        $this->createAddOns();
        $this->createPricingPageContent();

        $this->command?->info('✓ Plans seeded: Starter (99), Professional (299), Enterprise (699) + 12 add-ons + pricing page content.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Plans
    // ─────────────────────────────────────────────────────────────────────────

    private function createPlans(): array
    {
        return [

            // ── Starter ─────────────────────────────────────────────────────
            [
                'name'              => 'Starter',
                'name_ar'           => 'المبتدئ',
                'slug'              => 'starter',
                'description'       => 'Perfect for single-location boutiques and small shops just getting started.',
                'description_ar'    => 'مثالي للمتاجر الصغيرة ذات الموقع الواحد التي تبدأ للتو.',
                'monthly_price'     => 99.00,
                'annual_price'      => 950.00,
                'trial_days'        => 14,
                'grace_period_days' => 3,
                'is_active'         => true,
                'is_highlighted'    => false,
                'softpos_free_eligible'         => true,
                'softpos_free_threshold'        => null,
                'softpos_free_threshold_amount' => 5000.000, // 5,000 SAR in monthly SoftPOS sales
                'softpos_free_threshold_period' => 'monthly',
                'sort_order'        => 1,
                'features' => [
                    // Core POS
                    ['feature_key' => 'pos',                    'name' => 'Point of Sale',              'name_ar' => 'نقطة البيع',                      'is_enabled' => true],
                    ['feature_key' => 'zatca_phase2',           'name' => 'ZATCA Phase 2',               'name_ar' => 'زاتكا المرحلة الثانية',            'is_enabled' => true],
                    ['feature_key' => 'inventory',              'name' => 'Inventory Management',        'name_ar' => 'إدارة المخزون',                   'is_enabled' => true],
                    ['feature_key' => 'reports_basic',          'name' => 'Basic Reports',               'name_ar' => 'التقارير الأساسية',               'is_enabled' => true],
                    ['feature_key' => 'barcode_scanning',       'name' => 'Barcode Scanning',            'name_ar' => 'مسح الباركود',                    'is_enabled' => true],
                    ['feature_key' => 'cash_drawer',            'name' => 'Cash Drawer',                 'name_ar' => 'درج النقد',                       'is_enabled' => true],
                    ['feature_key' => 'customer_display',       'name' => 'Customer Display',            'name_ar' => 'شاشة العميل',                     'is_enabled' => true],
                    ['feature_key' => 'receipt_printing',       'name' => 'Receipt Printing',            'name_ar' => 'طباعة الإيصالات',                 'is_enabled' => true],
                    ['feature_key' => 'offline_mode',           'name' => 'Offline Mode',                'name_ar' => 'وضع عدم الاتصال',                 'is_enabled' => true],
                    ['feature_key' => 'mada_payments',          'name' => 'Mada & Card Payments',        'name_ar' => 'مدفوعات مدى والبطاقات',           'is_enabled' => true],
                    // Advanced features
                    ['feature_key' => 'reports_advanced',       'name' => 'Advanced Analytics',          'name_ar' => 'التحليلات المتقدمة',              'is_enabled' => false],
                    ['feature_key' => 'multi_branch',           'name' => 'Multi-Branch',                'name_ar' => 'متعدد الفروع',                    'is_enabled' => false],
                    ['feature_key' => 'delivery_integration',   'name' => 'Delivery Integration',        'name_ar' => 'تكامل التوصيل',                  'is_enabled' => false],
                    ['feature_key' => 'customer_management',    'name' => 'Customer Management',         'name_ar' => 'إدارة العملاء',                   'is_enabled' => true],
                    ['feature_key' => 'customer_loyalty',       'name' => 'Customer Loyalty',            'name_ar' => 'ولاء العملاء',                    'is_enabled' => false],
                    ['feature_key' => 'api_access',             'name' => 'API Access',                  'name_ar' => 'وصول API',                        'is_enabled' => false],
                    ['feature_key' => 'white_label',            'name' => 'White Label',                 'name_ar' => 'العلامة التجارية الخاصة',         'is_enabled' => false],
                    ['feature_key' => 'priority_support',       'name' => 'Priority Support',            'name_ar' => 'الدعم الأولوي',                   'is_enabled' => false],
                    ['feature_key' => 'dedicated_manager',      'name' => 'Dedicated Manager',           'name_ar' => 'مدير مخصص',                       'is_enabled' => false],
                    ['feature_key' => 'custom_integrations',    'name' => 'Custom Integrations',         'name_ar' => 'تكاملات مخصصة',                  'is_enabled' => false],
                    ['feature_key' => 'sla_guarantee',          'name' => 'SLA Guarantee',               'name_ar' => 'ضمان مستوى الخدمة',              'is_enabled' => false],
                    // AI & Tools
                    ['feature_key' => 'wameed_ai',              'name' => 'Wameed AI',                   'name_ar' => 'وميض الذكاء الاصطناعي',           'is_enabled' => false],
                    ['feature_key' => 'cashier_gamification',   'name' => 'Cashier Gamification',        'name_ar' => 'ألعاب الكاشير',                   'is_enabled' => false],
                    ['feature_key' => 'pos_customization',      'name' => 'POS Customization',           'name_ar' => 'تخصيص نقطة البيع',               'is_enabled' => false],
                    ['feature_key' => 'companion_app',          'name' => 'Companion App',               'name_ar' => 'تطبيق المالك',                    'is_enabled' => false],
                    ['feature_key' => 'installments',           'name' => 'Installment Payments',        'name_ar' => 'الدفع بالتقسيط',                 'is_enabled' => false],
                    ['feature_key' => 'accounting',             'name' => 'Accounting',                  'name_ar' => 'المحاسبة',                        'is_enabled' => false],
                    // Catalog add-ons
                    ['feature_key' => 'product_modifiers',      'name' => 'Product Modifiers',           'name_ar' => 'إضافات المنتج',                   'is_enabled' => true],
                    ['feature_key' => 'supplier_management',    'name' => 'Supplier Management',         'name_ar' => 'إدارة الموردين',                  'is_enabled' => true],
                    ['feature_key' => 'product_variants',       'name' => 'Product Variants',            'name_ar' => 'متغيرات المنتج',                  'is_enabled' => false],
                    ['feature_key' => 'combo_products',         'name' => 'Combo Products',              'name_ar' => 'منتجات الكومبو',                  'is_enabled' => false],
                    ['feature_key' => 'bulk_import',            'name' => 'Bulk CSV Import',             'name_ar' => 'استيراد CSV الجماعي',            'is_enabled' => false],
                    ['feature_key' => 'barcode_label_printing', 'name' => 'Barcode Label Printing',     'name_ar' => 'طباعة ملصقات الباركود',           'is_enabled' => true],
                    // Promotions & marketing
                    ['feature_key' => 'promotions_coupons',     'name' => 'Promotions & Coupons',        'name_ar' => 'العروض والقسائم',                 'is_enabled' => true],
                    ['feature_key' => 'promotions_advanced',    'name' => 'Advanced Promotions (BOGO, Bundles, Happy Hour)', 'name_ar' => 'عروض متقدمة', 'is_enabled' => false],
                    // Industry verticals
                    ['feature_key' => 'industry_restaurant',    'name' => 'Restaurant Features',         'name_ar' => 'ميزات المطاعم',                  'is_enabled' => false],
                    ['feature_key' => 'industry_bakery',        'name' => 'Bakery Features',             'name_ar' => 'ميزات المخابز',                  'is_enabled' => false],
                    ['feature_key' => 'industry_pharmacy',      'name' => 'Pharmacy Features',           'name_ar' => 'ميزات الصيدليات',                'is_enabled' => false],
                    ['feature_key' => 'industry_electronics',   'name' => 'Electronics Features',        'name_ar' => 'ميزات الإلكترونيات',             'is_enabled' => false],
                    ['feature_key' => 'industry_florist',       'name' => 'Florist Features',            'name_ar' => 'ميزات الزهور',                   'is_enabled' => false],
                    ['feature_key' => 'industry_jewelry',       'name' => 'Jewelry Features',            'name_ar' => 'ميزات المجوهرات',                'is_enabled' => false],
                ],
                'limits' => [
                    ['limit_key' => 'products',                 'limit_value' => 500,     'price_per_extra_unit' => null],
                    ['limit_key' => 'staff_members',            'limit_value' => 3,       'price_per_extra_unit' => null],
                    ['limit_key' => 'cashier_terminals',        'limit_value' => 1,       'price_per_extra_unit' => 49.00],
                    ['limit_key' => 'branches',                 'limit_value' => 1,       'price_per_extra_unit' => null],
                    ['limit_key' => 'transactions_per_month',   'limit_value' => 2000,    'price_per_extra_unit' => null],
                    ['limit_key' => 'storage_mb',               'limit_value' => 500,     'price_per_extra_unit' => null],
                    ['limit_key' => 'pdf_reports_per_month',    'limit_value' => 10,      'price_per_extra_unit' => null],
                ],
            ],

            // ── Professional ─────────────────────────────────────────────────
            [
                'name'              => 'Professional',
                'name_ar'           => 'الاحترافي',
                'slug'              => 'professional',
                'description'       => 'Ideal for growing supermarkets and multi-cashier retail stores.',
                'description_ar'    => 'مثالي للسوبرماركت المتنامية ومتاجر التجزئة متعددة الكاشير.',
                'monthly_price'     => 299.00,
                'annual_price'      => 2870.00,
                'trial_days'        => 14,
                'grace_period_days' => 7,
                'is_active'         => true,
                'is_highlighted'    => true,
                'softpos_free_eligible'         => true,
                'softpos_free_threshold'        => null,
                'softpos_free_threshold_amount' => 10000.000, // 10,000 SAR in monthly SoftPOS sales
                'softpos_free_threshold_period' => 'monthly',
                'sort_order'        => 2,
                'features' => [
                    // Core POS
                    ['feature_key' => 'pos',                    'name' => 'Point of Sale',              'name_ar' => 'نقطة البيع',                      'is_enabled' => true],
                    ['feature_key' => 'zatca_phase2',           'name' => 'ZATCA Phase 2',               'name_ar' => 'زاتكا المرحلة الثانية',            'is_enabled' => true],
                    ['feature_key' => 'inventory',              'name' => 'Inventory Management',        'name_ar' => 'إدارة المخزون',                   'is_enabled' => true],
                    ['feature_key' => 'reports_basic',          'name' => 'Basic Reports',               'name_ar' => 'التقارير الأساسية',               'is_enabled' => true],
                    ['feature_key' => 'barcode_scanning',       'name' => 'Barcode Scanning',            'name_ar' => 'مسح الباركود',                    'is_enabled' => true],
                    ['feature_key' => 'cash_drawer',            'name' => 'Cash Drawer',                 'name_ar' => 'درج النقد',                       'is_enabled' => true],
                    ['feature_key' => 'customer_display',       'name' => 'Customer Display',            'name_ar' => 'شاشة العميل',                     'is_enabled' => true],
                    ['feature_key' => 'receipt_printing',       'name' => 'Receipt Printing',            'name_ar' => 'طباعة الإيصالات',                 'is_enabled' => true],
                    ['feature_key' => 'offline_mode',           'name' => 'Offline Mode',                'name_ar' => 'وضع عدم الاتصال',                 'is_enabled' => true],
                    ['feature_key' => 'mada_payments',          'name' => 'Mada & Card Payments',        'name_ar' => 'مدفوعات مدى والبطاقات',           'is_enabled' => true],
                    // Advanced features
                    ['feature_key' => 'reports_advanced',       'name' => 'Advanced Analytics',          'name_ar' => 'التحليلات المتقدمة',              'is_enabled' => true],
                    ['feature_key' => 'multi_branch',           'name' => 'Multi-Branch',                'name_ar' => 'متعدد الفروع',                    'is_enabled' => true],
                    ['feature_key' => 'delivery_integration',   'name' => 'Delivery Integration',        'name_ar' => 'تكامل التوصيل',                  'is_enabled' => true],
                    ['feature_key' => 'customer_management',    'name' => 'Customer Management',         'name_ar' => 'إدارة العملاء',                   'is_enabled' => true],
                    ['feature_key' => 'customer_loyalty',       'name' => 'Customer Loyalty',            'name_ar' => 'ولاء العملاء',                    'is_enabled' => true],
                    ['feature_key' => 'api_access',             'name' => 'API Access',                  'name_ar' => 'وصول API',                        'is_enabled' => false],
                    ['feature_key' => 'white_label',            'name' => 'White Label',                 'name_ar' => 'العلامة التجارية الخاصة',         'is_enabled' => false],
                    ['feature_key' => 'priority_support',       'name' => 'Priority Support',            'name_ar' => 'الدعم الأولوي',                   'is_enabled' => false],
                    ['feature_key' => 'dedicated_manager',      'name' => 'Dedicated Manager',           'name_ar' => 'مدير مخصص',                       'is_enabled' => false],
                    ['feature_key' => 'custom_integrations',    'name' => 'Custom Integrations',         'name_ar' => 'تكاملات مخصصة',                  'is_enabled' => false],
                    ['feature_key' => 'sla_guarantee',          'name' => 'SLA Guarantee',               'name_ar' => 'ضمان مستوى الخدمة',              'is_enabled' => false],
                    // AI & Tools
                    ['feature_key' => 'wameed_ai',              'name' => 'Wameed AI',                   'name_ar' => 'وميض الذكاء الاصطناعي',           'is_enabled' => true],
                    ['feature_key' => 'cashier_gamification',   'name' => 'Cashier Gamification',        'name_ar' => 'ألعاب الكاشير',                   'is_enabled' => true],
                    ['feature_key' => 'pos_customization',      'name' => 'POS Customization',           'name_ar' => 'تخصيص نقطة البيع',               'is_enabled' => true],
                    ['feature_key' => 'companion_app',          'name' => 'Companion App',               'name_ar' => 'تطبيق المالك',                    'is_enabled' => true],
                    ['feature_key' => 'installments',           'name' => 'Installment Payments',        'name_ar' => 'الدفع بالتقسيط',                 'is_enabled' => true],
                    ['feature_key' => 'accounting',             'name' => 'Accounting',                  'name_ar' => 'المحاسبة',                        'is_enabled' => true],
                    // Catalog add-ons
                    ['feature_key' => 'product_modifiers',      'name' => 'Product Modifiers',           'name_ar' => 'إضافات المنتج',                   'is_enabled' => true],
                    ['feature_key' => 'supplier_management',    'name' => 'Supplier Management',         'name_ar' => 'إدارة الموردين',                  'is_enabled' => true],
                    ['feature_key' => 'product_variants',       'name' => 'Product Variants',            'name_ar' => 'متغيرات المنتج',                  'is_enabled' => true],
                    ['feature_key' => 'combo_products',         'name' => 'Combo Products',              'name_ar' => 'منتجات الكومبو',                  'is_enabled' => true],
                    ['feature_key' => 'bulk_import',            'name' => 'Bulk CSV Import',             'name_ar' => 'استيراد CSV الجماعي',            'is_enabled' => true],
                    ['feature_key' => 'barcode_label_printing', 'name' => 'Barcode Label Printing',     'name_ar' => 'طباعة ملصقات الباركود',           'is_enabled' => true],
                    // Promotions & marketing
                    ['feature_key' => 'promotions_coupons',     'name' => 'Promotions & Coupons',        'name_ar' => 'العروض والقسائم',                 'is_enabled' => true],
                    ['feature_key' => 'promotions_advanced',    'name' => 'Advanced Promotions (BOGO, Bundles, Happy Hour)', 'name_ar' => 'عروض متقدمة', 'is_enabled' => true],
                    // Industry verticals
                    ['feature_key' => 'industry_restaurant',    'name' => 'Restaurant Features',         'name_ar' => 'ميزات المطاعم',                  'is_enabled' => true],
                    ['feature_key' => 'industry_bakery',        'name' => 'Bakery Features',             'name_ar' => 'ميزات المخابز',                  'is_enabled' => true],
                    ['feature_key' => 'industry_pharmacy',      'name' => 'Pharmacy Features',           'name_ar' => 'ميزات الصيدليات',                'is_enabled' => false],
                    ['feature_key' => 'industry_electronics',   'name' => 'Electronics Features',        'name_ar' => 'ميزات الإلكترونيات',             'is_enabled' => false],
                    ['feature_key' => 'industry_florist',       'name' => 'Florist Features',            'name_ar' => 'ميزات الزهور',                   'is_enabled' => false],
                    ['feature_key' => 'industry_jewelry',       'name' => 'Jewelry Features',            'name_ar' => 'ميزات المجوهرات',                'is_enabled' => false],
                ],
                'limits' => [
                    ['limit_key' => 'products',                 'limit_value' => 5000,    'price_per_extra_unit' => 0.02],
                    ['limit_key' => 'staff_members',            'limit_value' => 15,      'price_per_extra_unit' => null],
                    ['limit_key' => 'cashier_terminals',        'limit_value' => 5,       'price_per_extra_unit' => 49.00],
                    ['limit_key' => 'branches',                 'limit_value' => 3,       'price_per_extra_unit' => 99.00],
                    ['limit_key' => 'transactions_per_month',   'limit_value' => 20000,   'price_per_extra_unit' => null],
                    ['limit_key' => 'storage_mb',               'limit_value' => 10000,   'price_per_extra_unit' => 0.10],
                    ['limit_key' => 'pdf_reports_per_month',    'limit_value' => -1,      'price_per_extra_unit' => null],
                ],
            ],

            // ── Enterprise ───────────────────────────────────────────────────
            [
                'name'              => 'Enterprise',
                'name_ar'           => 'المؤسسات',
                'slug'              => 'enterprise',
                'description'       => 'For retail chains with 5+ branches requiring full customisation and SLA-backed support.',
                'description_ar'    => 'لسلاسل التجزئة ذات 5 فروع أو أكثر التي تحتاج إلى تخصيص كامل ودعم مضمون.',
                'monthly_price'     => 699.00,
                'annual_price'      => 6710.00,
                'trial_days'        => 30,
                'grace_period_days' => 14,
                'is_active'         => true,
                'is_highlighted'    => false,
                'softpos_free_eligible'         => true,
                'softpos_free_threshold'        => null,
                'softpos_free_threshold_amount' => 20000.000, // 20,000 SAR in monthly SoftPOS sales
                'softpos_free_threshold_period' => 'monthly',
                'sort_order'        => 3,
                'features' => [
                    // Core POS
                    ['feature_key' => 'pos',                    'name' => 'Point of Sale',              'name_ar' => 'نقطة البيع',                      'is_enabled' => true],
                    ['feature_key' => 'zatca_phase2',           'name' => 'ZATCA Phase 2',               'name_ar' => 'زاتكا المرحلة الثانية',            'is_enabled' => true],
                    ['feature_key' => 'inventory',              'name' => 'Inventory Management',        'name_ar' => 'إدارة المخزون',                   'is_enabled' => true],
                    ['feature_key' => 'reports_basic',          'name' => 'Basic Reports',               'name_ar' => 'التقارير الأساسية',               'is_enabled' => true],
                    ['feature_key' => 'barcode_scanning',       'name' => 'Barcode Scanning',            'name_ar' => 'مسح الباركود',                    'is_enabled' => true],
                    ['feature_key' => 'cash_drawer',            'name' => 'Cash Drawer',                 'name_ar' => 'درج النقد',                       'is_enabled' => true],
                    ['feature_key' => 'customer_display',       'name' => 'Customer Display',            'name_ar' => 'شاشة العميل',                     'is_enabled' => true],
                    ['feature_key' => 'receipt_printing',       'name' => 'Receipt Printing',            'name_ar' => 'طباعة الإيصالات',                 'is_enabled' => true],
                    ['feature_key' => 'offline_mode',           'name' => 'Offline Mode',                'name_ar' => 'وضع عدم الاتصال',                 'is_enabled' => true],
                    ['feature_key' => 'mada_payments',          'name' => 'Mada & Card Payments',        'name_ar' => 'مدفوعات مدى والبطاقات',           'is_enabled' => true],
                    // Advanced features
                    ['feature_key' => 'reports_advanced',       'name' => 'Advanced Analytics',          'name_ar' => 'التحليلات المتقدمة',              'is_enabled' => true],
                    ['feature_key' => 'multi_branch',           'name' => 'Multi-Branch',                'name_ar' => 'متعدد الفروع',                    'is_enabled' => true],
                    ['feature_key' => 'delivery_integration',   'name' => 'Delivery Integration',        'name_ar' => 'تكامل التوصيل',                  'is_enabled' => true],
                    ['feature_key' => 'customer_management',    'name' => 'Customer Management',         'name_ar' => 'إدارة العملاء',                   'is_enabled' => true],
                    ['feature_key' => 'customer_loyalty',       'name' => 'Customer Loyalty',            'name_ar' => 'ولاء العملاء',                    'is_enabled' => true],
                    ['feature_key' => 'api_access',             'name' => 'API Access',                  'name_ar' => 'وصول API',                        'is_enabled' => true],
                    ['feature_key' => 'white_label',            'name' => 'White Label',                 'name_ar' => 'العلامة التجارية الخاصة',         'is_enabled' => true],
                    ['feature_key' => 'priority_support',       'name' => 'Priority Support',            'name_ar' => 'الدعم الأولوي',                   'is_enabled' => true],
                    ['feature_key' => 'dedicated_manager',      'name' => 'Dedicated Manager',           'name_ar' => 'مدير مخصص',                       'is_enabled' => true],
                    ['feature_key' => 'custom_integrations',    'name' => 'Custom Integrations',         'name_ar' => 'تكاملات مخصصة',                  'is_enabled' => true],
                    ['feature_key' => 'sla_guarantee',          'name' => 'SLA Guarantee',               'name_ar' => 'ضمان مستوى الخدمة',              'is_enabled' => true],
                    // AI & Tools
                    ['feature_key' => 'wameed_ai',              'name' => 'Wameed AI',                   'name_ar' => 'وميض الذكاء الاصطناعي',           'is_enabled' => true],
                    ['feature_key' => 'cashier_gamification',   'name' => 'Cashier Gamification',        'name_ar' => 'ألعاب الكاشير',                   'is_enabled' => true],
                    ['feature_key' => 'pos_customization',      'name' => 'POS Customization',           'name_ar' => 'تخصيص نقطة البيع',               'is_enabled' => true],
                    ['feature_key' => 'companion_app',          'name' => 'Companion App',               'name_ar' => 'تطبيق المالك',                    'is_enabled' => true],
                    ['feature_key' => 'installments',           'name' => 'Installment Payments',        'name_ar' => 'الدفع بالتقسيط',                 'is_enabled' => true],
                    ['feature_key' => 'accounting',             'name' => 'Accounting',                  'name_ar' => 'المحاسبة',                        'is_enabled' => true],
                    // Catalog add-ons
                    ['feature_key' => 'product_modifiers',      'name' => 'Product Modifiers',           'name_ar' => 'إضافات المنتج',                   'is_enabled' => true],
                    ['feature_key' => 'supplier_management',    'name' => 'Supplier Management',         'name_ar' => 'إدارة الموردين',                  'is_enabled' => true],
                    ['feature_key' => 'product_variants',       'name' => 'Product Variants',            'name_ar' => 'متغيرات المنتج',                  'is_enabled' => true],
                    ['feature_key' => 'combo_products',         'name' => 'Combo Products',              'name_ar' => 'منتجات الكومبو',                  'is_enabled' => true],
                    ['feature_key' => 'bulk_import',            'name' => 'Bulk CSV Import',             'name_ar' => 'استيراد CSV الجماعي',            'is_enabled' => true],
                    ['feature_key' => 'barcode_label_printing', 'name' => 'Barcode Label Printing',     'name_ar' => 'طباعة ملصقات الباركود',           'is_enabled' => true],
                    // Promotions & marketing
                    ['feature_key' => 'promotions_coupons',     'name' => 'Promotions & Coupons',        'name_ar' => 'العروض والقسائم',                 'is_enabled' => true],
                    ['feature_key' => 'promotions_advanced',    'name' => 'Advanced Promotions (BOGO, Bundles, Happy Hour)', 'name_ar' => 'عروض متقدمة', 'is_enabled' => true],
                    // Industry verticals
                    ['feature_key' => 'industry_restaurant',    'name' => 'Restaurant Features',         'name_ar' => 'ميزات المطاعم',                  'is_enabled' => true],
                    ['feature_key' => 'industry_bakery',        'name' => 'Bakery Features',             'name_ar' => 'ميزات المخابز',                  'is_enabled' => true],
                    ['feature_key' => 'industry_pharmacy',      'name' => 'Pharmacy Features',           'name_ar' => 'ميزات الصيدليات',                'is_enabled' => true],
                    ['feature_key' => 'industry_electronics',   'name' => 'Electronics Features',        'name_ar' => 'ميزات الإلكترونيات',             'is_enabled' => true],
                    ['feature_key' => 'industry_florist',       'name' => 'Florist Features',            'name_ar' => 'ميزات الزهور',                   'is_enabled' => true],
                    ['feature_key' => 'industry_jewelry',       'name' => 'Jewelry Features',            'name_ar' => 'ميزات المجوهرات',                'is_enabled' => true],
                ],
                'limits' => [
                    ['limit_key' => 'products',                 'limit_value' => -1,      'price_per_extra_unit' => null],
                    ['limit_key' => 'staff_members',            'limit_value' => -1,      'price_per_extra_unit' => null],
                    ['limit_key' => 'cashier_terminals',        'limit_value' => -1,      'price_per_extra_unit' => null],
                    ['limit_key' => 'branches',                 'limit_value' => -1,      'price_per_extra_unit' => null],
                    ['limit_key' => 'transactions_per_month',   'limit_value' => -1,      'price_per_extra_unit' => null],
                    ['limit_key' => 'storage_mb',               'limit_value' => -1,      'price_per_extra_unit' => null],
                    ['limit_key' => 'pdf_reports_per_month',    'limit_value' => -1,      'price_per_extra_unit' => null],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Add-Ons  (12 items)
    // ─────────────────────────────────────────────────────────────────────────

    private function createAddOns(): void
    {
        $addOns = [
            [
                'name'          => 'Extra Cashier Terminal',
                'name_ar'       => 'كاشير إضافي',
                'slug'          => 'extra-cashier',
                'monthly_price' => 49.00,
                'description'   => 'Add one additional cashier terminal to your store with full POS access.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Extra Branch',
                'name_ar'       => 'فرع إضافي',
                'slug'          => 'extra-branch',
                'monthly_price' => 99.00,
                'description'   => 'Expand to one additional store location with full inventory sync.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Delivery Integration',
                'name_ar'       => 'تكامل التوصيل',
                'slug'          => 'delivery-integration',
                'monthly_price' => 79.00,
                'description'   => 'Connect to HungerStation, Jahez, Keeta, Talabat and other delivery platforms.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Advanced Analytics & Reports',
                'name_ar'       => 'تحليلات وتقارير متقدمة',
                'slug'          => 'advanced-analytics',
                'monthly_price' => 59.00,
                'description'   => 'Unlock advanced dashboards, custom date ranges, product performance, and hour-by-hour sales charts.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Customer Loyalty Program',
                'name_ar'       => 'برنامج ولاء العملاء',
                'slug'          => 'customer-loyalty',
                'monthly_price' => 49.00,
                'description'   => 'Points, tiers, discount campaigns and customer profile management.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Kitchen Display System (KDS)',
                'name_ar'       => 'شاشة عرض المطبخ (KDS)',
                'slug'          => 'kitchen-display',
                'monthly_price' => 39.00,
                'description'   => 'Route orders directly to kitchen display screens with preparation timers.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Barcode Label Printer',
                'name_ar'       => 'طابعة ملصقات باركود',
                'slug'          => 'label-printer',
                'monthly_price' => 29.00,
                'description'   => 'Print shelf labels, barcode stickers and weight labels directly from the dashboard.',
                'is_active'     => true,
            ],
            [
                'name'          => 'SMS & WhatsApp Notifications',
                'name_ar'       => 'إشعارات SMS وواتساب',
                'slug'          => 'sms-whatsapp',
                'monthly_price' => 39.00,
                'description'   => 'Send order confirmations, low-stock alerts and loyalty rewards via SMS and WhatsApp.',
                'is_active'     => true,
            ],
            [
                'name'          => 'API Access & Webhooks',
                'name_ar'       => 'وصول API وـ Webhooks',
                'slug'          => 'api-access',
                'monthly_price' => 99.00,
                'description'   => 'Full REST API access with webhooks for real-time integration with ERP, accounting and custom systems.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Priority 24/7 Support',
                'name_ar'       => 'دعم أولوي 24/7',
                'slug'          => 'priority-support',
                'monthly_price' => 79.00,
                'description'   => '24-hour phone, WhatsApp, and remote desktop support with a guaranteed 1-hour response time.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Extra Cloud Storage (50 GB)',
                'name_ar'       => 'مساحة سحابية إضافية (50 جيجابايت)',
                'slug'          => 'extra-storage-50gb',
                'monthly_price' => 19.00,
                'description'   => 'Expand your cloud storage by 50 GB for receipts, invoices and product images.',
                'is_active'     => true,
            ],
            [
                'name'          => 'Dedicated Account Manager',
                'name_ar'       => 'مدير حساب مخصص',
                'slug'          => 'dedicated-manager',
                'monthly_price' => 149.00,
                'description'   => 'A dedicated WameedPOS specialist who knows your business and proactively manages your account.',
                'is_active'     => true,
            ],
        ];

        foreach ($addOns as $addOn) {
            PlanAddOn::updateOrCreate(['slug' => $addOn['slug']], $addOn);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pricing Page Content (rich marketing copy for each plan)
    // ─────────────────────────────────────────────────────────────────────────

    private function createPricingPageContent(): void
    {
        $plans = SubscriptionPlan::whereIn('slug', ['starter', 'professional', 'enterprise'])->get()->keyBy('slug');

        if ($plans->isEmpty()) {
            $this->command?->warn('No plans found — skipping pricing page content seeding.');
            return;
        }

        $contents = [

            // ── Starter ──────────────────────────────────────────────────────
            'starter' => [
                'hero_title'            => 'Start Selling Today',
                'hero_title_ar'         => 'ابدأ البيع اليوم',
                'hero_subtitle'         => 'Everything a small shop needs to run smoothly — ZATCA compliant from day one.',
                'hero_subtitle_ar'      => 'كل ما يحتاجه المتجر الصغير للعمل بسلاسة — متوافق مع زاتكا من اليوم الأول.',
                'highlight_badge'       => null,
                'is_highlighted'        => false,
                'cta_label'             => 'Start 14-Day Free Trial',
                'cta_label_ar'          => 'ابدأ التجربة المجانية 14 يوم',
                'cta_secondary_label'   => 'See all features',
                'cta_secondary_label_ar'=> 'اطلع على جميع الميزات',
                'price_suffix'          => 'SAR/month',
                'price_suffix_ar'       => 'ريال/شهريًا',
                'annual_discount_label' => 'Save 20% with annual billing',
                'annual_discount_label_ar'=> 'وفّر 20% بالدفع السنوي',
                'trial_label'           => '14-day free trial, no credit card required',
                'trial_label_ar'        => 'تجربة مجانية 14 يومًا دون بطاقة ائتمانية',
                'money_back_days'       => 14,
                'color_theme'           => 'gray',
                'card_icon'             => 'storefront',
                'is_published'          => true,
                'sort_order'            => 1,
                'feature_bullet_list'   => [
                    ['text' => '1 Cashier Terminal',                    'text_ar' => 'كاشير واحد',                          'icon' => 'point_of_sale',       'is_included' => true],
                    ['text' => 'ZATCA Phase 2 E-Invoicing',             'text_ar' => 'فوترة إلكترونية زاتكا المرحلة الثانية','icon' => 'qr_code_2',           'is_included' => true],
                    ['text' => 'Inventory Management (500 products)',   'text_ar' => 'إدارة مخزون (500 منتج)',              'icon' => 'inventory_2',         'is_included' => true],
                    ['text' => 'Barcode & Receipt Printing',            'text_ar' => 'طباعة باركود وإيصالات',               'icon' => 'print',               'is_included' => true],
                    ['text' => 'Mada & Card Payments',                  'text_ar' => 'مدفوعات مدى وبطاقات',                 'icon' => 'payments',            'is_included' => true],
                    ['text' => 'Offline Mode',                          'text_ar' => 'وضع عدم الاتصال',                    'icon' => 'wifi_off',            'is_included' => true],
                    ['text' => 'Basic Sales Reports',                   'text_ar' => 'تقارير مبيعات أساسية',                'icon' => 'bar_chart',           'is_included' => true],
                    ['text' => 'Email Support',                         'text_ar' => 'دعم بالبريد الإلكتروني',              'icon' => 'mail',                'is_included' => true],
                    ['text' => 'Multi-Branch',                          'text_ar' => 'متعدد الفروع',                        'icon' => 'store',               'is_included' => false],
                    ['text' => 'Delivery App Integration',              'text_ar' => 'تكامل تطبيقات التوصيل',               'icon' => 'delivery_dining',     'is_included' => false],
                    ['text' => 'Advanced Analytics',                    'text_ar' => 'تحليلات متقدمة',                      'icon' => 'analytics',           'is_included' => false],
                    ['text' => 'API Access',                            'text_ar' => 'وصول API',                            'icon' => 'api',                 'is_included' => false],
                ],
                'feature_categories'    => [
                    [
                        'name' => 'POS & Payments', 'name_ar' => 'نقطة البيع والمدفوعات',
                        'features' => [
                            ['name' => 'Cashier terminals',       'name_ar' => 'أجهزة الكاشير',         'limit' => '1',           'is_included' => true],
                            ['name' => 'Offline mode',            'name_ar' => 'وضع عدم الاتصال',      'limit' => '✓',           'is_included' => true],
                            ['name' => 'Mada / Visa / MC',        'name_ar' => 'مدى / فيزا / ماستركارد','limit' => '✓',           'is_included' => true],
                            ['name' => 'STC Pay / Apple Pay',     'name_ar' => 'STC Pay / Apple Pay',   'limit' => '✓',           'is_included' => true],
                            ['name' => 'Split payment',           'name_ar' => 'الدفع المجزأ',           'limit' => '✓',           'is_included' => true],
                        ],
                    ],
                    [
                        'name' => 'Inventory', 'name_ar' => 'المخزون',
                        'features' => [
                            ['name' => 'Products',                'name_ar' => 'المنتجات',               'limit' => '500',          'is_included' => true],
                            ['name' => 'Barcode scanning',        'name_ar' => 'مسح الباركود',           'limit' => '✓',           'is_included' => true],
                            ['name' => 'Low stock alerts',        'name_ar' => 'تنبيهات نفاد المخزون', 'limit' => '✓',           'is_included' => true],
                            ['name' => 'Branch transfers',        'name_ar' => 'تحويلات بين الفروع',    'limit' => '—',           'is_included' => false],
                        ],
                    ],
                    [
                        'name' => 'Reports & Analytics', 'name_ar' => 'التقارير والتحليلات',
                        'features' => [
                            ['name' => 'Daily sales summary',     'name_ar' => 'ملخص المبيعات اليومية', 'limit' => '✓',           'is_included' => true],
                            ['name' => 'Cashier reports',         'name_ar' => 'تقارير الكاشير',         'limit' => '✓',           'is_included' => true],
                            ['name' => 'Advanced dashboards',     'name_ar' => 'لوحات تحليلية متقدمة', 'limit' => '—',           'is_included' => false],
                            ['name' => 'PDF export',              'name_ar' => 'تصدير PDF',              'limit' => '10/mo',       'is_included' => true],
                        ],
                    ],
                    [
                        'name' => 'Support', 'name_ar' => 'الدعم',
                        'features' => [
                            ['name' => 'Email support',           'name_ar' => 'دعم بريد إلكتروني',     'limit' => '✓',           'is_included' => true],
                            ['name' => 'WhatsApp support',        'name_ar' => 'دعم واتساب',             'limit' => '—',           'is_included' => false],
                            ['name' => '24/7 phone support',      'name_ar' => 'دعم هاتفي 24/7',         'limit' => '—',           'is_included' => false],
                        ],
                    ],
                ],
                'comparison_highlights' => [
                    ['feature' => 'Cashier terminals',       'feature_ar' => 'أجهزة الكاشير',         'value' => '1'],
                    ['feature' => 'Branches',                'feature_ar' => 'الفروع',                 'value' => '1'],
                    ['feature' => 'Products',                'feature_ar' => 'المنتجات',               'value' => '500'],
                    ['feature' => 'Staff accounts',          'feature_ar' => 'حسابات الموظفين',        'value' => '3'],
                    ['feature' => 'ZATCA Phase 2',           'feature_ar' => 'زاتكا المرحلة الثانية', 'value' => '✓'],
                    ['feature' => 'Delivery integration',    'feature_ar' => 'تكامل التوصيل',          'value' => '—'],
                    ['feature' => 'Advanced analytics',      'feature_ar' => 'تحليلات متقدمة',         'value' => '—'],
                    ['feature' => 'API access',              'feature_ar' => 'وصول API',                'value' => '—'],
                ],
                'faq' => [
                    ['question' => 'Is ZATCA Phase 2 really included in the Starter plan?', 'question_ar' => 'هل زاتكا المرحلة الثانية مشمولة فعلاً في خطة المبتدئ؟', 'answer' => 'Yes. Every WameedPOS plan, including Starter, comes with full ZATCA Phase 2 compliance built in — automatic QR codes, cryptographic stamping and direct FATOORA portal integration.', 'answer_ar' => 'نعم. كل خطط WameedPOS، بما فيها المبتدئ، تأتي مع امتثال كامل لزاتكا المرحلة الثانية — رموز QR تلقائية، وختم تشفيري، وتكامل مباشر مع بوابة فاتورة.'],
                    ['question' => 'Can I add more terminals later?', 'question_ar' => 'هل يمكنني إضافة أجهزة كاشير لاحقًا؟', 'answer' => 'Yes. You can add extra cashier terminals at any time for 49 SAR/month each from our add-ons section.', 'answer_ar' => 'نعم. يمكنك إضافة أجهزة كاشير إضافية في أي وقت بـ 49 ريال/شهر لكل جهاز.'],
                ],
            ],

            // ── Professional ─────────────────────────────────────────────────
            'professional' => [
                'hero_title'            => 'Scale Your Business',
                'hero_title_ar'         => 'طوّر أعمالك',
                'hero_subtitle'         => 'Multi-terminal, multi-branch retailing with delivery platform integration.',
                'hero_subtitle_ar'      => 'تجزئة متعددة الأجهزة والفروع مع تكامل منصات التوصيل.',
                'highlight_badge'       => 'Most Popular',
                'highlight_badge_ar'    => 'الأكثر اختيارًا',
                'is_highlighted'        => true,
                'highlight_color'       => '#FF6B00',
                'cta_label'             => 'Start 14-Day Free Trial',
                'cta_label_ar'          => 'ابدأ التجربة المجانية 14 يوم',
                'cta_secondary_label'   => 'Talk to sales',
                'cta_secondary_label_ar'=> 'تحدث مع فريق المبيعات',
                'price_suffix'          => 'SAR/month',
                'price_suffix_ar'       => 'ريال/شهريًا',
                'annual_discount_label' => 'Save 20% with annual billing',
                'annual_discount_label_ar'=> 'وفّر 20% بالدفع السنوي',
                'trial_label'           => '14-day free trial, no credit card required',
                'trial_label_ar'        => 'تجربة مجانية 14 يومًا دون بطاقة ائتمانية',
                'money_back_days'       => 14,
                'color_theme'           => 'primary',
                'card_icon'             => 'trending_up',
                'is_published'          => true,
                'sort_order'            => 2,
                'feature_bullet_list'   => [
                    ['text' => 'Up to 5 Cashier Terminals',              'text_ar' => 'حتى 5 أجهزة كاشير',                  'icon' => 'point_of_sale',       'is_included' => true,  'is_highlighted' => true],
                    ['text' => 'ZATCA Phase 2 E-Invoicing',              'text_ar' => 'فوترة إلكترونية زاتكا المرحلة الثانية','icon' => 'qr_code_2',           'is_included' => true],
                    ['text' => 'Up to 3 Branches',                       'text_ar' => 'حتى 3 فروع',                          'icon' => 'store',               'is_included' => true,  'is_highlighted' => true],
                    ['text' => 'Inventory Management (5,000 products)',  'text_ar' => 'إدارة مخزون (5000 منتج)',             'icon' => 'inventory_2',         'is_included' => true],
                    ['text' => 'Delivery App Integration',               'text_ar' => 'تكامل تطبيقات التوصيل',              'icon' => 'delivery_dining',     'is_included' => true,  'is_highlighted' => true],
                    ['text' => 'Advanced Analytics & Reports',           'text_ar' => 'تحليلات وتقارير متقدمة',              'icon' => 'analytics',           'is_included' => true],
                    ['text' => 'Customer Loyalty Program',               'text_ar' => 'برنامج ولاء العملاء',                 'icon' => 'loyalty',             'is_included' => true],
                    ['text' => 'SMS & WhatsApp Notifications',           'text_ar' => 'إشعارات SMS وواتساب',                 'icon' => 'chat',                'is_included' => true],
                    ['text' => 'Phone & WhatsApp Support (Business hrs)','text_ar' => 'دعم هاتفي وواتساب (ساعات العمل)',     'icon' => 'support_agent',       'is_included' => true],
                    ['text' => 'API Access',                             'text_ar' => 'وصول API',                            'icon' => 'api',                 'is_included' => false],
                    ['text' => 'White Label',                            'text_ar' => 'علامة تجارية خاصة',                  'icon' => 'branding_watermark',  'is_included' => false],
                    ['text' => 'Dedicated Account Manager',              'text_ar' => 'مدير حساب مخصص',                     'icon' => 'person_pin',          'is_included' => false],
                ],
                'feature_categories'    => [
                    [
                        'name' => 'POS & Payments', 'name_ar' => 'نقطة البيع والمدفوعات',
                        'features' => [
                            ['name' => 'Cashier terminals',       'name_ar' => 'أجهزة الكاشير',         'limit' => 'Up to 5',     'is_included' => true,  'is_highlighted' => true],
                            ['name' => 'Offline mode',            'name_ar' => 'وضع عدم الاتصال',      'limit' => '✓',           'is_included' => true],
                            ['name' => 'Mada / Visa / MC',        'name_ar' => 'مدى / فيزا / ماستركارد','limit' => '✓',           'is_included' => true],
                            ['name' => 'STC Pay / Apple Pay',     'name_ar' => 'STC Pay / Apple Pay',   'limit' => '✓',           'is_included' => true],
                            ['name' => 'Split payment',           'name_ar' => 'الدفع المجزأ',           'limit' => '✓',           'is_included' => true],
                        ],
                    ],
                    [
                        'name' => 'Inventory', 'name_ar' => 'المخزون',
                        'features' => [
                            ['name' => 'Products',                'name_ar' => 'المنتجات',               'limit' => '5,000',       'is_included' => true],
                            ['name' => 'Barcode scanning',        'name_ar' => 'مسح الباركود',           'limit' => '✓',           'is_included' => true],
                            ['name' => 'Low stock alerts',        'name_ar' => 'تنبيهات نفاد المخزون', 'limit' => '✓',           'is_included' => true],
                            ['name' => 'Branch transfers',        'name_ar' => 'تحويلات بين الفروع',    'limit' => '✓',           'is_included' => true,  'is_highlighted' => true],
                        ],
                    ],
                    [
                        'name' => 'Reports & Analytics', 'name_ar' => 'التقارير والتحليلات',
                        'features' => [
                            ['name' => 'Daily sales summary',     'name_ar' => 'ملخص المبيعات اليومية', 'limit' => '✓',           'is_included' => true],
                            ['name' => 'Cashier reports',         'name_ar' => 'تقارير الكاشير',         'limit' => '✓',           'is_included' => true],
                            ['name' => 'Advanced dashboards',     'name_ar' => 'لوحات تحليلية متقدمة', 'limit' => '✓',           'is_included' => true,  'is_highlighted' => true],
                            ['name' => 'PDF export',              'name_ar' => 'تصدير PDF',              'limit' => 'Unlimited',   'is_included' => true],
                        ],
                    ],
                    [
                        'name' => 'Support', 'name_ar' => 'الدعم',
                        'features' => [
                            ['name' => 'Email support',           'name_ar' => 'دعم بريد إلكتروني',     'limit' => '✓',           'is_included' => true],
                            ['name' => 'WhatsApp support',        'name_ar' => 'دعم واتساب',             'limit' => 'Business hrs', 'is_included' => true, 'is_highlighted' => true],
                            ['name' => '24/7 phone support',      'name_ar' => 'دعم هاتفي 24/7',         'limit' => '—',           'is_included' => false],
                        ],
                    ],
                ],
                'comparison_highlights' => [
                    ['feature' => 'Cashier terminals',       'feature_ar' => 'أجهزة الكاشير',         'value' => 'Up to 5'],
                    ['feature' => 'Branches',                'feature_ar' => 'الفروع',                 'value' => 'Up to 3'],
                    ['feature' => 'Products',                'feature_ar' => 'المنتجات',               'value' => '5,000'],
                    ['feature' => 'Staff accounts',          'feature_ar' => 'حسابات الموظفين',        'value' => '15'],
                    ['feature' => 'ZATCA Phase 2',           'feature_ar' => 'زاتكا المرحلة الثانية', 'value' => '✓'],
                    ['feature' => 'Delivery integration',    'feature_ar' => 'تكامل التوصيل',          'value' => '✓'],
                    ['feature' => 'Advanced analytics',      'feature_ar' => 'تحليلات متقدمة',         'value' => '✓'],
                    ['feature' => 'API access',              'feature_ar' => 'وصول API',                'value' => 'Add-on'],
                ],
                'faq' => [
                    ['question' => 'Can I add more than 5 cashiers?', 'question_ar' => 'هل يمكنني إضافة أكثر من 5 كاشير؟', 'answer' => 'Yes — each extra cashier terminal costs 49 SAR/month as an add-on. You can add as many as you need.', 'answer_ar' => 'نعم — كل كاشير إضافي بـ 49 ريال/شهر كإضافة. يمكنك إضافة ما تحتاج.'],
                    ['question' => 'Does the plan include all delivery apps?', 'question_ar' => 'هل الخطة تشمل جميع تطبيقات التوصيل؟', 'answer' => 'Yes. The Professional plan includes integration with HungerStation, Jahez, Keeta, Talabat, Noon Food and other major Saudi delivery platforms, all managed from one screen.', 'answer_ar' => 'نعم. تشمل خطة الاحترافي التكامل مع هنقرستيشن وجاهز وكيتا وطلبات ونون فود وغيرها، من شاشة واحدة.'],
                ],
            ],

            // ── Enterprise ───────────────────────────────────────────────────
            'enterprise' => [
                'hero_title'            => 'Run Your Retail Empire',
                'hero_title_ar'         => 'أدر إمبراطورية تجزئتك',
                'hero_subtitle'         => 'Unlimited scale with custom integrations, SLA guarantee and a dedicated team.',
                'hero_subtitle_ar'      => 'توسع غير محدود مع تكاملات مخصصة وضمان SLA وفريق مخصص.',
                'highlight_badge'       => null,
                'is_highlighted'        => false,
                'cta_label'             => 'Start 30-Day Free Trial',
                'cta_label_ar'          => 'ابدأ التجربة المجانية 30 يوم',
                'cta_secondary_label'   => 'Request custom demo',
                'cta_secondary_label_ar'=> 'اطلب عرضًا مخصصًا',
                'price_suffix'          => 'SAR/month',
                'price_suffix_ar'       => 'ريال/شهريًا',
                'annual_discount_label' => 'Save 20% with annual billing',
                'annual_discount_label_ar'=> 'وفّر 20% بالدفع السنوي',
                'trial_label'           => '30-day free trial — full feature access',
                'trial_label_ar'        => 'تجربة مجانية 30 يومًا — وصول كامل لجميع الميزات',
                'money_back_days'       => 30,
                'color_theme'           => 'dark',
                'card_icon'             => 'corporate_fare',
                'is_published'          => true,
                'sort_order'            => 3,
                'feature_bullet_list'   => [
                    ['text' => 'Unlimited Cashier Terminals',             'text_ar' => 'أجهزة كاشير غير محدودة',              'icon' => 'point_of_sale',       'is_included' => true,  'is_highlighted' => true],
                    ['text' => 'ZATCA Phase 2 E-Invoicing',              'text_ar' => 'فوترة إلكترونية زاتكا المرحلة الثانية','icon' => 'qr_code_2',           'is_included' => true],
                    ['text' => 'Unlimited Branches',                     'text_ar' => 'فروع غير محدودة',                     'icon' => 'store',               'is_included' => true,  'is_highlighted' => true],
                    ['text' => 'Unlimited Products',                     'text_ar' => 'منتجات غير محدودة',                   'icon' => 'inventory_2',         'is_included' => true],
                    ['text' => 'All Delivery Platform Integrations',     'text_ar' => 'تكامل مع جميع منصات التوصيل',         'icon' => 'delivery_dining',     'is_included' => true],
                    ['text' => 'Full Analytics & Custom Reports',        'text_ar' => 'تحليلات كاملة وتقارير مخصصة',         'icon' => 'analytics',           'is_included' => true],
                    ['text' => 'API Access & Webhooks',                  'text_ar' => 'وصول API والـ Webhooks',               'icon' => 'api',                 'is_included' => true,  'is_highlighted' => true],
                    ['text' => 'White Label (Custom Branding)',          'text_ar' => 'علامة تجارية خاصة',                  'icon' => 'branding_watermark',  'is_included' => true,  'is_highlighted' => true],
                    ['text' => 'Custom ERP / SAP Integrations',         'text_ar' => 'تكاملات ERP/SAP مخصصة',               'icon' => 'integration_instructions','is_included' => true],
                    ['text' => 'Dedicated Account Manager',             'text_ar' => 'مدير حساب مخصص',                     'icon' => 'person_pin',          'is_included' => true],
                    ['text' => '24/7 Priority Phone Support + SLA',     'text_ar' => 'دعم هاتفي 24/7 + ضمان SLA',            'icon' => 'support_agent',       'is_included' => true,  'is_highlighted' => true],
                    ['text' => 'On-site Setup & Staff Training',        'text_ar' => 'تركيب وتدريب في الموقع',               'icon' => 'engineering',         'is_included' => true],
                ],
                'feature_categories'    => [
                    [
                        'name' => 'POS & Payments', 'name_ar' => 'نقطة البيع والمدفوعات',
                        'features' => [
                            ['name' => 'Cashier terminals',       'name_ar' => 'أجهزة الكاشير',         'limit' => 'Unlimited',   'is_included' => true,  'is_highlighted' => true],
                            ['name' => 'Offline mode',            'name_ar' => 'وضع عدم الاتصال',      'limit' => '✓',           'is_included' => true],
                            ['name' => 'Mada / Visa / MC',        'name_ar' => 'مدى / فيزا / ماستركارد','limit' => '✓',           'is_included' => true],
                            ['name' => 'STC Pay / Apple Pay',     'name_ar' => 'STC Pay / Apple Pay',   'limit' => '✓',           'is_included' => true],
                            ['name' => 'Split payment',           'name_ar' => 'الدفع المجزأ',           'limit' => '✓',           'is_included' => true],
                        ],
                    ],
                    [
                        'name' => 'Inventory', 'name_ar' => 'المخزون',
                        'features' => [
                            ['name' => 'Products',                'name_ar' => 'المنتجات',               'limit' => 'Unlimited',   'is_included' => true,  'is_highlighted' => true],
                            ['name' => 'Barcode scanning',        'name_ar' => 'مسح الباركود',           'limit' => '✓',           'is_included' => true],
                            ['name' => 'Low stock alerts',        'name_ar' => 'تنبيهات نفاد المخزون', 'limit' => '✓',           'is_included' => true],
                            ['name' => 'Branch transfers',        'name_ar' => 'تحويلات بين الفروع',    'limit' => 'Unlimited',   'is_included' => true],
                        ],
                    ],
                    [
                        'name' => 'Reports & Analytics', 'name_ar' => 'التقارير والتحليلات',
                        'features' => [
                            ['name' => 'Daily sales summary',     'name_ar' => 'ملخص المبيعات اليومية', 'limit' => '✓',           'is_included' => true],
                            ['name' => 'Cashier reports',         'name_ar' => 'تقارير الكاشير',         'limit' => '✓',           'is_included' => true],
                            ['name' => 'Advanced dashboards',     'name_ar' => 'لوحات تحليلية متقدمة', 'limit' => '✓',           'is_included' => true],
                            ['name' => 'Custom reports',          'name_ar' => 'تقارير مخصصة',           'limit' => '✓',           'is_included' => true,  'is_highlighted' => true],
                            ['name' => 'PDF export',              'name_ar' => 'تصدير PDF',              'limit' => 'Unlimited',   'is_included' => true],
                        ],
                    ],
                    [
                        'name' => 'Support', 'name_ar' => 'الدعم',
                        'features' => [
                            ['name' => 'Email support',           'name_ar' => 'دعم بريد إلكتروني',     'limit' => '✓',           'is_included' => true],
                            ['name' => 'WhatsApp support',        'name_ar' => 'دعم واتساب',             'limit' => '24/7',        'is_included' => true],
                            ['name' => '24/7 phone support + SLA','name_ar' => 'دعم هاتفي 24/7 + SLA',  'limit' => '1-hr SLA',    'is_included' => true,  'is_highlighted' => true],
                        ],
                    ],
                ],
                'comparison_highlights' => [
                    ['feature' => 'Cashier terminals',       'feature_ar' => 'أجهزة الكاشير',         'value' => 'Unlimited'],
                    ['feature' => 'Branches',                'feature_ar' => 'الفروع',                 'value' => 'Unlimited'],
                    ['feature' => 'Products',                'feature_ar' => 'المنتجات',               'value' => 'Unlimited'],
                    ['feature' => 'Staff accounts',          'feature_ar' => 'حسابات الموظفين',        'value' => 'Unlimited'],
                    ['feature' => 'ZATCA Phase 2',           'feature_ar' => 'زاتكا المرحلة الثانية', 'value' => '✓'],
                    ['feature' => 'Delivery integration',    'feature_ar' => 'تكامل التوصيل',          'value' => '✓'],
                    ['feature' => 'Advanced analytics',      'feature_ar' => 'تحليلات متقدمة',         'value' => '✓'],
                    ['feature' => 'API access',              'feature_ar' => 'وصول API',                'value' => '✓'],
                ],
                'faq' => [
                    ['question' => 'What does the SLA guarantee cover?', 'question_ar' => 'ما الذي يغطيه ضمان SLA؟', 'answer' => 'Enterprise SLA guarantees a 1-hour response time for critical issues and 99.9% platform uptime. If we miss the SLA, you receive service credits automatically.', 'answer_ar' => 'يضمن SLA للمؤسسات وقت استجابة ساعة واحدة للمشاكل الحرجة وتشغيل المنصة 99.9%. إذا لم نستوفِ SLA، تحصل على ائتمانات خدمة تلقائيًا.'],
                    ['question' => 'Can you integrate with our existing ERP or SAP system?', 'question_ar' => 'هل يمكنكم التكامل مع نظام ERP أو SAP الموجود لدينا؟', 'answer' => 'Yes. Our Enterprise onboarding team handles custom API integrations with SAP, Oracle, Microsoft Dynamics, and other ERP systems during the setup phase.', 'answer_ar' => 'نعم. يتولى فريق الإعداد لدينا تكاملات API المخصصة مع SAP وOracle وMicrosoft Dynamics وغيرها خلال مرحلة الإعداد.'],
                ],
            ],
        ];

        foreach ($contents as $slug => $data) {
            $plan = $plans->get($slug);
            if (! $plan) continue;

            PricingPageContent::updateOrCreate(
                ['subscription_plan_id' => $plan->id],
                $data,
            );
        }
    }
}
