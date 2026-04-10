<?php

namespace App\Domain\WameedAI\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AIFeatureDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            // ─── Inventory ───
            ['slug' => 'smart_reorder', 'name' => 'Smart Reorder', 'name_ar' => 'إعادة الطلب الذكي', 'description' => 'AI-powered reorder point suggestions based on sales velocity', 'description_ar' => 'اقتراحات ذكية لنقاط إعادة الطلب بناءً على سرعة المبيعات', 'category' => 'inventory', 'icon' => 'refresh_rounded', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 50, 'monthly_limit' => 500],
            ['slug' => 'expiry_manager', 'name' => 'Expiry Manager', 'name_ar' => 'مدير الصلاحية', 'description' => 'Proactive expiry alerts and markdown suggestions', 'description_ar' => 'تنبيهات استباقية لانتهاء الصلاحية واقتراحات التخفيض', 'category' => 'inventory', 'icon' => 'timer_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 50, 'monthly_limit' => 500],
            ['slug' => 'dead_stock', 'name' => 'Dead Stock Detector', 'name_ar' => 'كاشف المخزون الراكد', 'description' => 'Identify products with no recent sales and suggest actions', 'description_ar' => 'تحديد المنتجات بدون مبيعات حديثة واقتراح إجراءات', 'category' => 'inventory', 'icon' => 'inventory_2_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'shrinkage_detection', 'name' => 'Shrinkage Detection', 'name_ar' => 'كشف الانكماش', 'description' => 'Detect inventory discrepancies and potential shrinkage', 'description_ar' => 'كشف اختلافات المخزون والانكماش المحتمل', 'category' => 'inventory', 'icon' => 'warning_amber_rounded', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'supplier_analysis', 'name' => 'Supplier Analysis', 'name_ar' => 'تحليل الموردين', 'description' => 'Evaluate supplier performance and suggest improvements', 'description_ar' => 'تقييم أداء الموردين واقتراح تحسينات', 'category' => 'inventory', 'icon' => 'local_shipping_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'seasonal_planning', 'name' => 'Seasonal Planning', 'name_ar' => 'التخطيط الموسمي', 'description' => 'Year-over-year seasonal demand analysis', 'description_ar' => 'تحليل الطلب الموسمي سنة بعد سنة', 'category' => 'inventory', 'icon' => 'calendar_month_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 10, 'monthly_limit' => 100],

            // ─── Sales ───
            ['slug' => 'daily_summary', 'name' => 'Daily Summary', 'name_ar' => 'الملخص اليومي', 'description' => 'AI-generated daily business performance summary', 'description_ar' => 'ملخص يومي تلقائي لأداء الأعمال', 'category' => 'sales', 'icon' => 'summarize_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 10, 'monthly_limit' => 300],
            ['slug' => 'sales_forecast', 'name' => 'Sales Forecast', 'name_ar' => 'توقع المبيعات', 'description' => 'Predict future sales based on historical data', 'description_ar' => 'توقع المبيعات المستقبلية بناءً على البيانات التاريخية', 'category' => 'sales', 'icon' => 'trending_up_rounded', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'peak_hours', 'name' => 'Peak Hours Analysis', 'name_ar' => 'تحليل ساعات الذروة', 'description' => 'Identify peak business hours for staffing optimization', 'description_ar' => 'تحديد ساعات الذروة لتحسين جدولة الموظفين', 'category' => 'sales', 'icon' => 'schedule_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'pricing_optimization', 'name' => 'Pricing Optimization', 'name_ar' => 'تحسين التسعير', 'description' => 'AI suggestions for optimal product pricing', 'description_ar' => 'اقتراحات ذكية لتسعير المنتجات الأمثل', 'category' => 'sales', 'icon' => 'price_change_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'bundle_suggestions', 'name' => 'Bundle Suggestions', 'name_ar' => 'اقتراحات الحزم', 'description' => 'Suggest product bundles based on co-purchase patterns', 'description_ar' => 'اقتراح حزم المنتجات بناءً على أنماط الشراء المشترك', 'category' => 'sales', 'icon' => 'shopping_basket_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'revenue_anomaly', 'name' => 'Revenue Anomaly Detection', 'name_ar' => 'كشف شذوذ الإيرادات', 'description' => 'Detect unusual revenue patterns and alert', 'description_ar' => 'كشف الأنماط غير العادية في الإيرادات والتنبيه', 'category' => 'sales', 'icon' => 'notification_important_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],

            // ─── Catalog ───
            ['slug' => 'product_categorization', 'name' => 'Product Categorization', 'name_ar' => 'تصنيف المنتجات', 'description' => 'Auto-categorize products using AI', 'description_ar' => 'تصنيف تلقائي للمنتجات باستخدام الذكاء الاصطناعي', 'category' => 'catalog', 'icon' => 'category_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 100, 'monthly_limit' => 1000],
            ['slug' => 'invoice_ocr', 'name' => 'Invoice OCR', 'name_ar' => 'قراءة الفواتير', 'description' => 'Extract structured data from invoice images', 'description_ar' => 'استخراج بيانات منظمة من صور الفواتير', 'category' => 'catalog', 'icon' => 'document_scanner_outlined', 'default_model' => 'gpt-4o', 'is_premium' => true, 'daily_limit' => 30, 'monthly_limit' => 300],
            ['slug' => 'product_description', 'name' => 'Product Description Generator', 'name_ar' => 'مولد وصف المنتجات', 'description' => 'Generate marketing descriptions for products', 'description_ar' => 'إنشاء أوصاف تسويقية للمنتجات', 'category' => 'catalog', 'icon' => 'description_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 50, 'monthly_limit' => 500],
            ['slug' => 'barcode_enrichment', 'name' => 'Barcode Enrichment', 'name_ar' => 'إثراء الباركود', 'description' => 'Look up product info from barcode', 'description_ar' => 'البحث عن معلومات المنتج من الباركود', 'category' => 'catalog', 'icon' => 'qr_code_scanner_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 100, 'monthly_limit' => 1000],

            // ─── Customer Intelligence ───
            ['slug' => 'customer_segmentation', 'name' => 'Customer Segmentation', 'name_ar' => 'تقسيم العملاء', 'description' => 'Segment customers into meaningful groups', 'description_ar' => 'تقسيم العملاء إلى مجموعات ذات معنى', 'category' => 'customer', 'icon' => 'people_outline_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 10, 'monthly_limit' => 100],
            ['slug' => 'churn_prediction', 'name' => 'Churn Prediction', 'name_ar' => 'توقع فقدان العملاء', 'description' => 'Identify customers at risk of leaving', 'description_ar' => 'تحديد العملاء المعرضين لخطر المغادرة', 'category' => 'customer', 'icon' => 'person_off_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 10, 'monthly_limit' => 100],
            ['slug' => 'personalized_promotions', 'name' => 'Personalized Promotions', 'name_ar' => 'العروض المخصصة', 'description' => 'Generate personalized promotion ideas per segment', 'description_ar' => 'إنشاء أفكار عروض مخصصة لكل شريحة', 'category' => 'customer', 'icon' => 'card_giftcard_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'spending_patterns', 'name' => 'Spending Patterns', 'name_ar' => 'أنماط الإنفاق', 'description' => 'Analyze individual customer spending behavior', 'description_ar' => 'تحليل سلوك إنفاق العملاء الفردي', 'category' => 'customer', 'icon' => 'analytics_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 50, 'monthly_limit' => 500],
            ['slug' => 'sentiment_analysis', 'name' => 'Sentiment Analysis', 'name_ar' => 'تحليل المشاعر', 'description' => 'Analyze customer feedback sentiment', 'description_ar' => 'تحليل مشاعر ملاحظات العملاء', 'category' => 'customer', 'icon' => 'sentiment_satisfied_alt_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],

            // ─── Operations ───
            ['slug' => 'smart_search', 'name' => 'Smart Search', 'name_ar' => 'البحث الذكي', 'description' => 'Natural language search across your store data', 'description_ar' => 'بحث بلغة طبيعية في بيانات متجرك', 'category' => 'operations', 'icon' => 'search_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 200, 'monthly_limit' => 3000],
            ['slug' => 'staff_performance', 'name' => 'Staff Performance', 'name_ar' => 'أداء الموظفين', 'description' => 'AI analysis of staff performance metrics', 'description_ar' => 'تحليل ذكي لمقاييس أداء الموظفين', 'category' => 'operations', 'icon' => 'badge_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'cashier_errors', 'name' => 'Cashier Error Detection', 'name_ar' => 'كشف أخطاء الكاشير', 'description' => 'Detect patterns of cashier errors and discrepancies', 'description_ar' => 'كشف أنماط أخطاء الكاشير والاختلافات', 'category' => 'operations', 'icon' => 'point_of_sale_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'efficiency_score', 'name' => 'Efficiency Score', 'name_ar' => 'نقاط الكفاءة', 'description' => 'Overall store efficiency score with breakdown', 'description_ar' => 'نقاط كفاءة المتجر الشاملة مع التفصيل', 'category' => 'operations', 'icon' => 'speed_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],

            // ─── Communication ───
            ['slug' => 'marketing_generator', 'name' => 'Marketing Generator', 'name_ar' => 'مولد التسويق', 'description' => 'Generate SMS and WhatsApp marketing messages', 'description_ar' => 'إنشاء رسائل تسويقية عبر SMS و WhatsApp', 'category' => 'communication', 'icon' => 'campaign_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 50, 'monthly_limit' => 500],
            ['slug' => 'social_content', 'name' => 'Social Content', 'name_ar' => 'محتوى اجتماعي', 'description' => 'Generate social media post content', 'description_ar' => 'إنشاء محتوى منشورات وسائل التواصل', 'category' => 'communication', 'icon' => 'share_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 50, 'monthly_limit' => 500],
            ['slug' => 'translation', 'name' => 'Translation', 'name_ar' => 'الترجمة', 'description' => 'AI-powered Arabic to English translation', 'description_ar' => 'ترجمة ذكية من العربية إلى الإنجليزية', 'category' => 'communication', 'icon' => 'translate_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 100, 'monthly_limit' => 2000],

            // ─── Financial ───
            ['slug' => 'margin_analyzer', 'name' => 'Margin Analyzer', 'name_ar' => 'محلل هوامش الربح', 'description' => 'Analyze product margins and suggest improvements', 'description_ar' => 'تحليل هوامش ربح المنتجات واقتراح تحسينات', 'category' => 'financial', 'icon' => 'account_balance_wallet_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'expense_analysis', 'name' => 'Expense Analysis', 'name_ar' => 'تحليل المصروفات', 'description' => 'Categorize and analyze business expenses', 'description_ar' => 'تصنيف وتحليل مصروفات الأعمال', 'category' => 'financial', 'icon' => 'receipt_long_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 20, 'monthly_limit' => 200],
            ['slug' => 'cashflow_prediction', 'name' => 'Cash Flow Prediction', 'name_ar' => 'توقع التدفق النقدي', 'description' => 'Predict future cash flow based on trends', 'description_ar' => 'توقع التدفق النقدي المستقبلي بناءً على الاتجاهات', 'category' => 'financial', 'icon' => 'account_balance_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 10, 'monthly_limit' => 100],

            // ─── Platform Admin ───
            ['slug' => 'store_health', 'name' => 'Store Health Score', 'name_ar' => 'نقاط صحة المتجر', 'description' => 'Platform-wide store health assessment', 'description_ar' => 'تقييم صحة المتاجر على مستوى المنصة', 'category' => 'platform', 'icon' => 'monitor_heart_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 10, 'monthly_limit' => 100],
            ['slug' => 'platform_trends', 'name' => 'Platform Trends', 'name_ar' => 'اتجاهات المنصة', 'description' => 'Cross-platform trend analysis', 'description_ar' => 'تحليل اتجاهات عبر المنصة', 'category' => 'platform', 'icon' => 'insights_outlined', 'default_model' => 'gpt-4o-mini', 'daily_limit' => 10, 'monthly_limit' => 100],
        ];

        foreach ($features as $feature) {
            DB::table('ai_feature_definitions')->updateOrInsert(
                ['slug' => $feature['slug']],
                array_merge($feature, [
                    'id' => DB::raw('gen_random_uuid()'),
                    'is_enabled' => $feature['is_enabled'] ?? true,
                    'is_premium' => $feature['is_premium'] ?? false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
