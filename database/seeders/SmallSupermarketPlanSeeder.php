<?php

namespace Database\Seeders;

use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * One-time seeder to insert the hidden "Small Supermarket" subscription plan.
 * Run with: php artisan db:seed --class=SmallSupermarketPlanSeeder
 */
class SmallSupermarketPlanSeeder extends Seeder
{
    public function run(): void
    {
        $planData = [
            'name'              => 'Small Supermarket',
            'name_ar'           => 'سوبرماركت صغير',
            'slug'              => 'small-supermarket',
            'description'       => 'Tailored for small grocery and supermarket stores. Includes the essentials: POS, inventory, basic reports, ZATCA compliance, and staff management — without the complexity of features they will never use.',
            'description_ar'    => 'مخصص للبقالات والسوبرماركت الصغيرة. يشمل الأساسيات: نقطة البيع، المخزون، التقارير الأساسية، الامتثال لزاتكا، وإدارة الموظفين — دون تعقيد الميزات غير المستخدمة.',
            'monthly_price'     => 149.00,
            'annual_price'      => 1430.00,
            'trial_days'        => 14,
            'grace_period_days' => 5,
            'is_active'         => true,
            'is_highlighted'    => false,
            'hide_from_public'          => true,
            'hide_unselected_features'  => true,
            'softpos_free_eligible'         => true,
            'softpos_free_threshold'        => null,
            'softpos_free_threshold_amount' => 5000.000,
            'softpos_free_threshold_period' => 'monthly',
            'sort_order'        => 4,
        ];

        $features = [
            // ─ Core POS ─────────────────────────────────────────────────────
            ['feature_key' => 'pos',                    'name' => 'Point of Sale',              'name_ar' => 'نقطة البيع',                      'is_enabled' => true],
            ['feature_key' => 'zatca_phase2',           'name' => 'ZATCA Phase 2',               'name_ar' => 'زاتكا المرحلة الثانية',            'is_enabled' => true],
            ['feature_key' => 'inventory',              'name' => 'Inventory Management',        'name_ar' => 'إدارة المخزون',                   'is_enabled' => true],
            ['feature_key' => 'reports_basic',          'name' => 'Basic Reports',               'name_ar' => 'التقارير الأساسية',               'is_enabled' => true],
            ['feature_key' => 'barcode_scanning',       'name' => 'Barcode Scanning',            'name_ar' => 'مسح الباركود',                    'is_enabled' => true],
            ['feature_key' => 'cash_drawer',            'name' => 'Cash Drawer',                 'name_ar' => 'درج النقد',                       'is_enabled' => false],
            ['feature_key' => 'customer_display',       'name' => 'Customer Display',            'name_ar' => 'شاشة العميل',                     'is_enabled' => true],
            ['feature_key' => 'receipt_printing',       'name' => 'Receipt Printing',            'name_ar' => 'طباعة الإيصالات',                 'is_enabled' => true],
            ['feature_key' => 'offline_mode',           'name' => 'Offline Mode',                'name_ar' => 'وضع عدم الاتصال',                 'is_enabled' => true],
            ['feature_key' => 'mada_payments',          'name' => 'Mada & Card Payments',        'name_ar' => 'مدفوعات مدى والبطاقات',           'is_enabled' => true],
            // ─ Advanced ─────────────────────────────────────────────────────
            ['feature_key' => 'reports_advanced',       'name' => 'Advanced Analytics',          'name_ar' => 'التحليلات المتقدمة',              'is_enabled' => true],
            ['feature_key' => 'multi_branch',           'name' => 'Multi-Branch',                'name_ar' => 'متعدد الفروع',                    'is_enabled' => true],
            ['feature_key' => 'delivery_integration',   'name' => 'Delivery Integration',        'name_ar' => 'تكامل التوصيل',                  'is_enabled' => false],
            ['feature_key' => 'customer_management',    'name' => 'Customer Management',         'name_ar' => 'إدارة العملاء',                   'is_enabled' => false],
            ['feature_key' => 'customer_loyalty',       'name' => 'Customer Loyalty',            'name_ar' => 'ولاء العملاء',                    'is_enabled' => true],
            ['feature_key' => 'api_access',             'name' => 'API Access',                  'name_ar' => 'وصول API',                        'is_enabled' => false],
            ['feature_key' => 'white_label',            'name' => 'White Label',                 'name_ar' => 'العلامة التجارية الخاصة',         'is_enabled' => false],
            ['feature_key' => 'priority_support',       'name' => 'Priority Support',            'name_ar' => 'الدعم الأولوي',                   'is_enabled' => false],
            ['feature_key' => 'dedicated_manager',      'name' => 'Dedicated Manager',           'name_ar' => 'مدير مخصص',                       'is_enabled' => false],
            ['feature_key' => 'custom_integrations',    'name' => 'Custom Integrations',         'name_ar' => 'تكاملات مخصصة',                  'is_enabled' => false],
            ['feature_key' => 'sla_guarantee',          'name' => 'SLA Guarantee',               'name_ar' => 'ضمان مستوى الخدمة',              'is_enabled' => false],
            // ─ AI & Tools ────────────────────────────────────────────────────
            ['feature_key' => 'wameed_ai',              'name' => 'Wameed AI',                   'name_ar' => 'وميض الذكاء الاصطناعي',           'is_enabled' => true],
            ['feature_key' => 'cashier_gamification',   'name' => 'Cashier Gamification',        'name_ar' => 'ألعاب الكاشير',                   'is_enabled' => false],
            ['feature_key' => 'pos_customization',      'name' => 'POS Customization',           'name_ar' => 'تخصيص نقطة البيع',               'is_enabled' => false],
            ['feature_key' => 'companion_app',          'name' => 'Companion App',               'name_ar' => 'تطبيق المالك',                    'is_enabled' => false],
            ['feature_key' => 'installments',           'name' => 'Installment Payments',        'name_ar' => 'الدفع بالتقسيط',                 'is_enabled' => false],
            ['feature_key' => 'accounting',             'name' => 'Accounting',                  'name_ar' => 'المحاسبة',                        'is_enabled' => true],
            // ─ Catalog ───────────────────────────────────────────────────────
            ['feature_key' => 'product_modifiers',      'name' => 'Product Modifiers',           'name_ar' => 'إضافات المنتج',                   'is_enabled' => false],
            ['feature_key' => 'supplier_management',    'name' => 'Supplier Management',         'name_ar' => 'إدارة الموردين',                  'is_enabled' => true],
            ['feature_key' => 'product_variants',       'name' => 'Product Variants',            'name_ar' => 'متغيرات المنتج',                  'is_enabled' => false],
            ['feature_key' => 'combo_products',         'name' => 'Combo Products',              'name_ar' => 'منتجات الكومبو',                  'is_enabled' => false],
            ['feature_key' => 'bulk_import',            'name' => 'Bulk CSV Import',             'name_ar' => 'استيراد CSV الجماعي',            'is_enabled' => true],
            ['feature_key' => 'barcode_label_printing', 'name' => 'Barcode Label Printing',      'name_ar' => 'طباعة ملصقات الباركود',           'is_enabled' => true],
            // ─ Promotions ────────────────────────────────────────────────────
            ['feature_key' => 'promotions_coupons',     'name' => 'Promotions & Coupons',        'name_ar' => 'العروض والقسائم',                 'is_enabled' => true],
            ['feature_key' => 'promotions_advanced',    'name' => 'Advanced Promotions',         'name_ar' => 'عروض متقدمة',                     'is_enabled' => false],
            // ─ Industry verticals (all off) ───────────────────────────────────
            ['feature_key' => 'industry_restaurant',    'name' => 'Restaurant Features',         'name_ar' => 'ميزات المطاعم',                  'is_enabled' => false],
            ['feature_key' => 'industry_bakery',        'name' => 'Bakery Features',             'name_ar' => 'ميزات المخابز',                  'is_enabled' => false],
            ['feature_key' => 'industry_pharmacy',      'name' => 'Pharmacy Features',           'name_ar' => 'ميزات الصيدليات',                'is_enabled' => false],
            ['feature_key' => 'industry_electronics',   'name' => 'Electronics Features',        'name_ar' => 'ميزات الإلكترونيات',             'is_enabled' => false],
            ['feature_key' => 'industry_florist',       'name' => 'Florist Features',            'name_ar' => 'ميزات الزهور',                   'is_enabled' => false],
            ['feature_key' => 'industry_jewelry',       'name' => 'Jewelry Features',            'name_ar' => 'ميزات المجوهرات',                'is_enabled' => false],
        ];

        $limits = [
            ['limit_key' => 'products',                 'limit_value' => 2000,    'price_per_extra_unit' => null],
            ['limit_key' => 'staff_members',            'limit_value' => 5,       'price_per_extra_unit' => null],
            ['limit_key' => 'cashier_terminals',        'limit_value' => 2,       'price_per_extra_unit' => 49.00],
            ['limit_key' => 'branches',                 'limit_value' => 1,       'price_per_extra_unit' => null],
            ['limit_key' => 'transactions_per_month',   'limit_value' => 5000,    'price_per_extra_unit' => null],
            ['limit_key' => 'storage_mb',               'limit_value' => 1000,    'price_per_extra_unit' => null],
            ['limit_key' => 'pdf_reports_per_month',    'limit_value' => 20,      'price_per_extra_unit' => null],
        ];

        $plan = SubscriptionPlan::updateOrCreate(
            ['slug' => 'small-supermarket'],
            $planData,
        );

        PlanFeatureToggle::where('subscription_plan_id', $plan->id)->delete();
        foreach ($features as $feature) {
            PlanFeatureToggle::create(['subscription_plan_id' => $plan->id, ...$feature]);
        }

        PlanLimit::where('subscription_plan_id', $plan->id)->delete();
        foreach ($limits as $limit) {
            PlanLimit::create(['subscription_plan_id' => $plan->id, ...$limit]);
        }

        $this->command?->info("✓ Small Supermarket plan seeded (id: {$plan->id}, hidden: yes, 149 SAR/mo).");
    }
}
