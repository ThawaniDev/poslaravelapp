#!/usr/bin/env python3
"""
translate_ai.py - Replace hardcoded AI strings with Laravel translation keys
Creates lang/en/ai.php and lang/ar/ai.php and updates blade/PHP files
"""

import os
import re
from pathlib import Path

# Define the project root
PROJECT_ROOT = Path("/Users/dogorshom/Desktop/Thawani/thawani/POS/poslaravelapp")
LANG_DIR = PROJECT_ROOT / "resources/lang"
BLADE_DIR = PROJECT_ROOT / "resources/views/filament/pages"
PAGES_DIR = PROJECT_ROOT / "app/Filament/Pages"

# Translation keys and values
TRANSLATIONS = {
    # Dashboard KPIs
    "todays_requests": {"en": "Today's Requests", "ar": "طلبات اليوم"},
    "total_label": {"en": "Total: ", "ar": "الإجمالي: "},
    "todays_raw_cost": {"en": "Today's Raw Cost", "ar": "تكلفة اليوم الخام"},
    "todays_billed": {"en": "Today's Billed", "ar": "ما تم فرضه اليوم"},
    "avg_latency": {"en": "Avg Latency", "ar": "متوسط زمن الاستجابة"},
    "ms": {"en": "ms", "ar": "ms"},
    "cache_hit_rate": {"en": "Cache Hit Rate", "ar": "معدل نجاح الذاكرة المؤقتة"},
    "platform_margin": {"en": "Platform Margin", "ar": "هامش المنصة"},
    "billed_raw": {"en": "Billed - Raw", "ar": "المفروض - الخام"},
    "features": {"en": "Features", "ar": "الميزات"},
    "enabled": {"en": "Enabled", "ar": "مفعل"},
    "active_stores_30d": {"en": "Active Stores (30d)", "ar": "المتاجر النشطة (آخر 30 يوم)"},
    "error_rate_7d": {"en": "Error Rate (7d)", "ar": "معدل الأخطاء (آخر 7 أيام)"},
    "avg_cost_per_request": {"en": "Avg Cost/Request", "ar": "متوسط التكلفة/الطلب"},
    
    # Dashboard Tables
    "top_features_30d": {"en": "Top Features (Last 30 Days)", "ar": "أفضل الميزات (آخر 30 يوم)"},
    "no_usage_data": {"en": "No usage data yet.", "ar": "لا توجد بيانات استخدام حتى الآن."},
    "feature": {"en": "Feature", "ar": "الميزة"},
    "requests": {"en": "Requests", "ar": "الطلبات"},
    "raw_cost": {"en": "Raw Cost", "ar": "التكلفة الخام"},
    "billed": {"en": "Billed", "ar": "المفروض"},
    "daily_trend_14d": {"en": "Daily Trend (Last 14 Days)", "ar": "الاتجاه اليومي (آخر 14 يوم)"},
    
    # Billing Page Tabs
    "overview": {"en": "Overview", "ar": "نظرة عامة"},
    "invoices": {"en": "Invoices", "ar": "الفواتير"},
    "store_configs": {"en": "Store Configs", "ar": "تكوينات المتجر"},
    "settings": {"en": "Settings", "ar": "الإعدادات"},
    
    # Billing Overview KPIs
    "total_revenue_billed": {"en": "Total Revenue (Billed)", "ar": "إجمالي الإيرادات (المفروضة)"},
    "raw_cost_openai": {"en": "Raw Cost (OpenAI)", "ar": "التكلفة الخام (OpenAI)"},
    "pending_revenue": {"en": "Pending Revenue", "ar": "الإيرادات المعلقة"},
    "this_month_billed": {"en": "This Month (Billed)", "ar": "هذا الشهر (المفروض)"},
    "raw_label": {"en": "Raw: ", "ar": "الخام: "},
    "ai_enabled_stores": {"en": "AI-Enabled Stores", "ar": "المتاجر المفعلة للذكاء الاصطناعي"},
    "overdue": {"en": "Overdue", "ar": "متأخر الدفع"},
    "total_invoices": {"en": "Total Invoices", "ar": "إجمالي الفواتير"},
    "paid": {"en": "Paid", "ar": "مدفوع"},
    "pending": {"en": "Pending", "ar": "معلق"},
    "generate_last_month_invoices": {"en": "Generate Last Month Invoices", "ar": "إنشاء فواتير الشهر الماضي"},
    
    # Invoice Table Headers
    "invoice_number": {"en": "Invoice #", "ar": "رقم الفاتورة"},
    "store": {"en": "Store", "ar": "المتجر"},
    "period": {"en": "Period", "ar": "الفترة"},
    "margin_percentage": {"en": "Margin %", "ar": "الهامش %"},
    "margin_dollar": {"en": "Margin $", "ar": "الهامش $"},
    "status": {"en": "Status", "ar": "الحالة"},
    "due_date": {"en": "Due Date", "ar": "تاريخ الاستحقاق"},
    "actions": {"en": "Actions", "ar": "الإجراءات"},
    "no_invoices_yet": {"en": "No invoices yet", "ar": "لا توجد فواتير حتى الآن"},
    
    # Invoice Actions
    "pay": {"en": "Pay", "ar": "الدفع"},
    "mark_paid": {"en": "Mark Paid", "ar": "تحديد كمدفوع"},
    "payment_reference": {"en": "Payment Reference", "ar": "مرجع الدفع"},
    "notes": {"en": "Notes", "ar": "الملاحظات"},
    "confirm_paid": {"en": "Confirm Paid", "ar": "تأكيد الدفع"},
    "cancel": {"en": "Cancel", "ar": "إلغاء"},
    "optional_notes": {"en": "Optional notes", "ar": "ملاحظات اختيارية"},
    
    # Store Configs Tab
    "store_ai_configurations": {"en": "Store AI Configurations", "ar": "تكوينات الذكاء الاصطناعي للمتجر"},
    "ai_enabled": {"en": "AI Enabled", "ar": "الذكاء الاصطناعي مفعل"},
    "monthly_limit": {"en": "Monthly Limit", "ar": "الحد الشهري"},
    "custom_margin": {"en": "Custom Margin", "ar": "هامش مخصص"},
    "updated": {"en": "Updated", "ar": "محدث"},
    "edit": {"en": "Edit", "ar": "تعديل"},
    "disable": {"en": "Disable", "ar": "تعطيل"},
    "enable": {"en": "Enable", "ar": "تفعيل"},
    "no_limit": {"en": "No limit", "ar": "لا يوجد حد"},
    "default": {"en": "Default", "ar": "الافتراضي"},
    "no_store_configs": {"en": "No store configurations yet", "ar": "لا توجد تكوينات متجر حتى الآن"},
    "save": {"en": "Save", "ar": "حفظ"},
    
    # Settings Tab
    "billing_settings": {"en": "Billing Settings", "ar": "إعدادات الفواتير"},
    "key": {"en": "Key", "ar": "المفتاح"},
    "value": {"en": "Value", "ar": "القيمة"},
    "description": {"en": "Description", "ar": "الوصف"},
    "delete": {"en": "Delete", "ar": "حذف"},
    "add": {"en": "Add", "ar": "إضافة"},
    "add_new_setting": {"en": "Add New Setting", "ar": "إضافة إعداد جديد"},
    "no_settings_configured": {"en": "No settings configured", "ar": "لم يتم تكوين أي إعدادات"},
    
    # Chats Page KPIs
    "total_chats": {"en": "Total Chats", "ar": "إجمالي المحادثات"},
    "todays_chats": {"en": "Today's Chats", "ar": "محادثات اليوم"},
    "total_messages": {"en": "Total Messages", "ar": "إجمالي الرسائل"},
    "avg_messages_per_chat": {"en": "Avg Messages/Chat", "ar": "متوسط الرسائل/محادثة"},
    
    # Chats Filters and Display
    "chats": {"en": "Chats", "ar": "المحادثات"},
    "from": {"en": "From", "ar": "من"},
    "to": {"en": "To", "ar": "إلى"},
    "no_chats_found": {"en": "No chats found", "ar": "لم يتم العثور على محادثات"},
    "chat_detail": {"en": "Chat Detail", "ar": "تفاصيل المحادثة"},
    "select_a_chat": {"en": "Select a chat", "ar": "اختر محادثة"},
    "msg": {"en": "msg", "ar": "رسالة"},
    "msgs": {"en": "msgs", "ar": "رسائل"},
    "select_chat_list": {"en": "Select a chat from the list to view messages", "ar": "اختر محادثة من القائمة لعرض الرسائل"},
    "user": {"en": "User", "ar": "المستخدم"},
    "assistant": {"en": "Assistant", "ar": "المساعد"},
    "system": {"en": "System", "ar": "النظام"},
    "tokens": {"en": "tokens", "ar": "الرموز"},
    
    # Store Intelligence KPIs
    "ai_active_stores": {"en": "AI-Active Stores", "ar": "المتاجر النشطة في الذكاء الاصطناعي"},
    "total_requests": {"en": "Total Requests", "ar": "إجمالي الطلبات"},
    "last_days_format": {"en": "Last {days}d: ", "ar": "آخر {days} أيام: "},
    "total_raw_cost": {"en": "Total Raw Cost", "ar": "إجمالي التكلفة الخام"},
    "total_billed": {"en": "Total Billed", "ar": "إجمالي المفروض"},
    "platform_margin_details": {"en": "Platform Margin", "ar": "هامش المنصة"},
    "search_stores": {"en": "Search stores by name, Arabic name, or slug...", "ar": "البحث عن المتاجر حسب الاسم أو الاسم بالعربية أو الرمز..."},
    "stores_with_ai_activity": {"en": "Stores with AI Activity", "ar": "المتاجر التي لها نشاط في الذكاء الاصطناعي"},
    "last_7_days": {"en": "Last 7 days", "ar": "آخر 7 أيام"},
    "last_14_days": {"en": "Last 14 days", "ar": "آخر 14 يوم"},
    "last_30_days": {"en": "Last 30 days", "ar": "آخر 30 يوم"},
    "last_60_days": {"en": "Last 60 days", "ar": "آخر 60 يوم"},
    "last_90_days": {"en": "Last 90 days", "ar": "آخر 90 يوم"},
    "last_year": {"en": "Last year", "ar": "السنة الماضية"},
    
    # Store Intelligence Table Headers
    "requests_period": {"en": "Requests ({days}d)", "ar": "الطلبات ({days}d)"},
    "all_time_requests": {"en": "All-Time Requests", "ar": "إجمالي الطلبات عبر الوقت"},
    "margin": {"en": "Margin", "ar": "الهامش"},
    "tokens_label": {"en": "Tokens", "ar": "الرموز"},
    "errors": {"en": "Errors", "ar": "الأخطاء"},
    "last_activity": {"en": "Last Activity", "ar": "آخر نشاط"},
    "no_ai_activity": {"en": "No stores with AI activity found", "ar": "لم يتم العثور على متاجر بها نشاط في الذكاء الاصطناعي"},
    
    # Store Detail Navigation
    "back_to_stores": {"en": "Back to Stores", "ar": "العودة إلى المتاجر"},
    "ai_disabled": {"en": "AI Disabled", "ar": "الذكاء الاصطناعي معطل"},
    "tabs": {
        "overview": {"en": "Overview", "ar": "نظرة عامة"},
        "features": {"en": "Features", "ar": "الميزات"},
        "billing": {"en": "Billing", "ar": "الفواتير"},
        "trends": {"en": "Trends", "ar": "الاتجاهات"},
        "chats": {"en": "Chats", "ar": "المحادثات"},
        "logs": {"en": "Logs", "ar": "السجلات"},
    },
    
    # Store Detail KPIs
    "today_label": {"en": "Today: ", "ar": "اليوم: "},
    "today_colon": {"en": "Today: ", "ar": "اليوم: "},
    "all_time_label": {"en": "(All Time)", "ar": "(عبر الوقت)"},
    "billed_cost_all_time": {"en": "Billed Cost (All Time)", "ar": "التكلفة المفروضة (عبر الوقت)"},
    "markup_percentage": {"en": "% markup", "ar": "٪ هامش"},
    "total_tokens": {"en": "Total Tokens", "ar": "إجمالي الرموز"},
    
    # Store Information Card
    "store_information": {"en": "Store Information", "ar": "معلومات المتجر"},
    "store_name": {"en": "Store Name", "ar": "اسم المتجر"},
    "arabic_name": {"en": "Arabic Name", "ar": "الاسم بالعربية"},
    "slug": {"en": "Slug", "ar": "الرمز"},
    "business_type": {"en": "Business Type", "ar": "نوع الأعمال"},
    "store_active": {"en": "Store Active", "ar": "المتجر نشط"},
    "active": {"en": "Active", "ar": "نشط"},
    "inactive": {"en": "Inactive", "ar": "غير نشط"},
    "store_created": {"en": "Store Created", "ar": "تاريخ إنشاء المتجر"},
    "first_ai_use": {"en": "First AI Use", "ar": "أول استخدام للذكاء الاصطناعي"},
    "last_ai_use": {"en": "Last AI Use", "ar": "آخر استخدام للذكاء الاصطناعي"},
    "never": {"en": "Never", "ar": "لم يتم"},
    
    # Billing Configuration Card
    "billing_configuration": {"en": "Billing Configuration", "ar": "تكوين الفواتير"},
    "ai_status": {"en": "AI Status", "ar": "حالة الذكاء الاصطناعي"},
    "disabled_reason": {"en": "Disabled Reason", "ar": "سبب التعطيل"},
    "enabled_at": {"en": "Enabled At", "ar": "تم التفعيل في"},
    "disabled_at": {"en": "Disabled At", "ar": "تم التعطيل في"},
    "no_ai_config": {"en": "No billing configuration set up for this store.", "ar": "لم يتم إعداد أي تكوين فواتير لهذا المتجر."},
    
    # Models Table
    "models_used": {"en": "Models Used (Last {days} Days)", "ar": "النماذج المستخدمة (آخر {days} يوم)"},
    "model": {"en": "Model", "ar": "النموذج"},
    "no_model_data": {"en": "No model usage data.", "ar": "لا توجد بيانات استخدام النموذج."},
    "unknown": {"en": "Unknown", "ar": "غير معروف"},
    
    # Features Usage Table
    "feature_usage": {"en": "Feature Usage (Last {days} Days)", "ar": "استخدام الميزات (آخر {days} يوم)"},
    "avg_latency_label": {"en": "Avg Latency", "ar": "متوسط زمن الاستجابة"},
    "cached": {"en": "Cached", "ar": "مخزن مؤقتاً"},
    "no_feature_data": {"en": "No feature usage data for this period.", "ar": "لا توجد بيانات استخدام ميزات لهذه الفترة."},
    
    # PHP Page Titles
    "wameed_ai_dashboard_title": {"en": "Wameed AI Dashboard", "ar": "لوحة تحكم Wameed الذكاء الاصطناعي"},
    "ai_billing_title": {"en": "AI Billing", "ar": "فواتير الذكاء الاصطناعي"},
    "ai_chat_analytics_title": {"en": "AI Chat Analytics", "ar": "تحليلات محادثات الذكاء الاصطناعي"},
}

def create_language_files():
    """Create lang/en/ai.php and lang/ar/ai.php"""
    # Ensure lang directory exists
    LANG_DIR.mkdir(parents=True, exist_ok=True)
    en_dir = LANG_DIR / "en"
    ar_dir = LANG_DIR / "ar"
    en_dir.mkdir(exist_ok=True)
    ar_dir.mkdir(exist_ok=True)
    
    # Generate English translation file
    en_content = "<?php\n\nreturn [\n"
    ar_content = "<?php\n\nreturn [\n"
    
    for key, translations in TRANSLATIONS.items():
        if isinstance(translations, dict) and 'en' in translations:
            en_val = translations['en'].replace("'", "\\'")
            ar_val = translations['ar'].replace("'", "\\'")
            en_content += f"    '{key}' => '{en_val}',\n"
            ar_content += f"    '{key}' => '{ar_val}',\n"
    
    en_content += "];\n"
    ar_content += "];\n"
    
    # Write files
    en_file = en_dir / "ai.php"
    ar_file = ar_dir / "ai.php"
    
    en_file.write_text(en_content)
    ar_file.write_text(ar_content)
    
    print(f"✓ Created {en_file}")
    print(f"✓ Created {ar_file}")

def replace_in_blade_files():
    """Replace hardcoded strings in blade files"""
    blade_files = [
        "wameed-ai-dashboard.blade.php",
        "wameed-ai-billing.blade.php",
        "wameed-ai-chats.blade.php",
        "wameed-ai-store-intelligence.blade.php",
    ]
    
    replacements = [
        # Dashboard
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Today\'s Requests</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.todays_requests\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Today\'s Raw Cost</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.todays_raw_cost\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Today\'s Billed</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.todays_billed\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Avg Latency</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.avg_latency\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Cache Hit Rate</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.cache_hit_rate\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Platform Margin</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.platform_margin\') }}</p>'),
        (r'<p class="text-xs text-gray-400">Billed - Raw</p>', 
         '<p class="text-xs text-gray-400">{{ __(\'ai.billed_raw\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Features</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.features\') }}</p>'),
        (r'<p class="text-xs text-gray-400">Enabled</p>', 
         '<p class="text-xs text-gray-400">{{ __(\'ai.enabled\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Active Stores \(30d\)</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.active_stores_30d\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Error Rate \(7d\)</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.error_rate_7d\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Avg Cost/Request</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.avg_cost_per_request\') }}</p>'),
        
        # Table headings
        (r'<x-filament::section heading="Top Features \(Last 30 Days\)">', 
         '<x-filament::section heading="{{ __(\'ai.top_features_30d\') }}">'),
        (r'<p class="text-sm text-gray-400">No usage data yet\.</p>', 
         '<p class="text-sm text-gray-400">{{ __(\'ai.no_usage_data\') }}</p>'),
        (r'<th class="pb-2 text-left font-medium text-gray-500 dark:text-gray-400">Feature</th>', 
         '<th class="pb-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __(\'ai.feature\') }}</th>'),
        (r'<th class="pb-2 text-right font-medium text-gray-500 dark:text-gray-400">Requests</th>', 
         '<th class="pb-2 text-right font-medium text-gray-500 dark:text-gray-400">{{ __(\'ai.requests\') }}</th>'),
        (r'<th class="pb-2 text-right font-medium text-gray-500 dark:text-gray-400">Raw Cost</th>', 
         '<th class="pb-2 text-right font-medium text-gray-500 dark:text-gray-400">{{ __(\'ai.raw_cost\') }}</th>'),
        (r'<th class="pb-2 text-right font-medium text-gray-500 dark:text-gray-400">Billed</th>', 
         '<th class="pb-2 text-right font-medium text-gray-500 dark:text-gray-400">{{ __(\'ai.billed\') }}</th>'),
        (r'<x-filament::section heading="Daily Trend \(Last 14 Days\)">', 
         '<x-filament::section heading="{{ __(\'ai.daily_trend_14d\') }}">'),
        
        # Billing tabs
        (r"'overview' => 'Overview'", r"'overview' => __(\'ai.overview\')"),
        (r"'invoices' => 'Invoices'", r"'invoices' => __(\'ai.invoices\')"),
        (r"'stores' => 'Store Configs'", r"'stores' => __(\'ai.store_configs\')"),
        (r"'settings' => 'Settings'", r"'settings' => __(\'ai.settings\')"),
        
        # Billing KPIs
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue \(Billed\)</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.total_revenue_billed\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Raw Cost \(OpenAI\)</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.raw_cost_openai\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Pending Revenue</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.pending_revenue\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">This Month \(Billed\)</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.this_month_billed\') }}</p>'),
        (r'<p class="text-xs text-gray-400">Raw: \$', '<p class="text-xs text-gray-400">{{ __(\'ai.raw_label\') }}\$'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">AI-Enabled Stores</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.ai_enabled_stores\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Total Invoices</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.total_invoices\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Paid</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.paid\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.pending\') }}</p>'),
        (r'Generate Last Month Invoices', 
         '{{ __(\'ai.generate_last_month_invoices\') }}'),
        
        # Invoice table headers
        (r'<th class="px-3 py-2 text-start font-medium text-gray-500">Invoice #</th>', 
         '<th class="px-3 py-2 text-start font-medium text-gray-500">{{ __(\'ai.invoice_number\') }}</th>'),
        (r'<th class="px-3 py-2 text-start font-medium text-gray-500">Store</th>', 
         '<th class="px-3 py-2 text-start font-medium text-gray-500">{{ __(\'ai.store\') }}</th>'),
        (r'<th class="px-3 py-2 text-start font-medium text-gray-500">Period</th>', 
         '<th class="px-3 py-2 text-start font-medium text-gray-500">{{ __(\'ai.period\') }}</th>'),
        (r'<th class="px-3 py-2 text-end font-medium text-gray-500">Margin %</th>', 
         '<th class="px-3 py-2 text-end font-medium text-gray-500">{{ __(\'ai.margin_percentage\') }}</th>'),
        (r'<th class="px-3 py-2 text-end font-medium text-gray-500">Margin \$</th>', 
         '<th class="px-3 py-2 text-end font-medium text-gray-500">{{ __(\'ai.margin_dollar\') }}</th>'),
        (r'<th class="px-3 py-2 text-center font-medium text-gray-500">Status</th>', 
         '<th class="px-3 py-2 text-center font-medium text-gray-500">{{ __(\'ai.status\') }}</th>'),
        (r'<th class="px-3 py-2 text-start font-medium text-gray-500">Due Date</th>', 
         '<th class="px-3 py-2 text-start font-medium text-gray-500">{{ __(\'ai.due_date\') }}</th>'),
        (r'<th class="px-3 py-2 text-center font-medium text-gray-500">Actions</th>', 
         '<th class="px-3 py-2 text-center font-medium text-gray-500">{{ __(\'ai.actions\') }}</th>'),
        
        # Invoice actions
        (r'<td colspan="{{ \$canManage \? 11 : 10 }}" class="px-3 py-8 text-center text-gray-400">No invoices yet</td>', 
         '<td colspan="{{ $canManage ? 11 : 10 }}" class="px-3 py-8 text-center text-gray-400">{{ __(\'ai.no_invoices_yet\') }}</td>'),
        (r'class="text-xs text-success-600 hover:text-success-800 font-medium">Pay</button>', 
         'class="text-xs text-success-600 hover:text-success-800 font-medium">{{ __(\'ai.pay\') }}</button>'),
        (r'class="text-xs text-danger-600 hover:text-danger-800 font-medium">Overdue</button>', 
         'class="text-xs text-danger-600 hover:text-danger-800 font-medium">{{ __(\'ai.overdue\') }}</button>'),
        (r'class="text-xs text-success-600 hover:text-success-800 font-medium">Mark Paid</button>', 
         'class="text-xs text-success-600 hover:text-success-800 font-medium">{{ __(\'ai.mark_paid\') }}</button>'),
        (r'<label class="text-xs font-medium text-gray-600">Payment Reference</label>', 
         '<label class="text-xs font-medium text-gray-600">{{ __(\'ai.payment_reference\') }}</label>'),
        (r'<label class="text-xs font-medium text-gray-600">Notes</label>', 
         '<label class="text-xs font-medium text-gray-600">{{ __(\'ai.notes\') }}</label>'),
        (r'placeholder="Optional notes"', r'placeholder="{{ __(\'ai.optional_notes\') }}"'),
        (r'wire:click="markInvoicePaid" size="sm" color="success">Confirm Paid</x-filament::button>', 
         'wire:click="markInvoicePaid" size="sm" color="success">{{ __(\'ai.confirm_paid\') }}</x-filament::button>'),
        (r'wire:click="cancelMarkPaid" size="sm" color="gray">Cancel</x-filament::button>', 
         'wire:click="cancelMarkPaid" size="sm" color="gray">{{ __(\'ai.cancel\') }}</x-filament::button>'),
        
        # Store Configs
        (r'<x-filament::section heading="Store AI Configurations">', 
         '<x-filament::section heading="{{ __(\'ai.store_ai_configurations\') }}">'),
        (r'<th class="px-3 py-2 text-center font-medium text-gray-500">AI Enabled</th>', 
         '<th class="px-3 py-2 text-center font-medium text-gray-500">{{ __(\'ai.ai_enabled\') }}</th>'),
        (r'<th class="px-3 py-2 text-end font-medium text-gray-500">Monthly Limit</th>', 
         '<th class="px-3 py-2 text-end font-medium text-gray-500">{{ __(\'ai.monthly_limit\') }}</th>'),
        (r'<th class="px-3 py-2 text-end font-medium text-gray-500">Custom Margin</th>', 
         '<th class="px-3 py-2 text-end font-medium text-gray-500">{{ __(\'ai.custom_margin\') }}</th>'),
        (r'<th class="px-3 py-2 text-start font-medium text-gray-500">Updated</th>', 
         '<th class="px-3 py-2 text-start font-medium text-gray-500">{{ __(\'ai.updated\') }}</th>'),
        
        # Store config inline display
        (r'<span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Enabled</span>', 
         '<span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __(\'ai.enabled\') }}</span>'),
        (r'<span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Disabled</span>', 
         '<span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __(\'ai.ai_disabled\') }}</span>'),
        (r"'No limit' : '\$'\.number_format", r"__(\'ai.no_limit\') : '$'.number_format"),
        (r"'Default' : '\$'", r"__(\'ai.default\') : '$'"),
        
        # Store config buttons
        (r'class="text-xs text-primary-600 hover:text-primary-800 font-medium">Edit</button>', 
         'class="text-xs text-primary-600 hover:text-primary-800 font-medium">{{ __(\'ai.edit\') }}</button>'),
        (r'class="text-xs text-danger-600 hover:text-danger-800 font-medium">\n                                                    {{ \$config->is_ai_enabled \? \'Disable\' : \'Enable\' }}\n                                                </button>', 
         'class="text-xs text-danger-600 hover:text-danger-800 font-medium">\n                                                    {{ $config->is_ai_enabled ? __(\'ai.disable\') : __(\'ai.enable\') }}\n                                                </button>'),
        (r'<td colspan="{{ \$canManage \? 7 : 6 }}" class="px-3 py-8 text-center text-gray-400">No store configurations yet</td>', 
         '<td colspan="{{ $canManage ? 7 : 6 }}" class="px-3 py-8 text-center text-gray-400">{{ __(\'ai.no_store_configs\') }}</td>'),
        
        # Settings Tab
        (r'<x-filament::section heading="Billing Settings">', 
         '<x-filament::section heading="{{ __(\'ai.billing_settings\') }}">'),
        (r'<th class="px-3 py-2 text-start font-medium text-gray-500">Key</th>', 
         '<th class="px-3 py-2 text-start font-medium text-gray-500">{{ __(\'ai.key\') }}</th>'),
        (r'<th class="px-3 py-2 text-start font-medium text-gray-500">Value</th>', 
         '<th class="px-3 py-2 text-start font-medium text-gray-500">{{ __(\'ai.value\') }}</th>'),
        (r'<th class="px-3 py-2 text-start font-medium text-gray-500">Description</th>', 
         '<th class="px-3 py-2 text-start font-medium text-gray-500">{{ __(\'ai.description\') }}</th>'),
        (r'<td colspan="{{ \$canManage \? 4 : 3 }}" class="px-3 py-8 text-center text-gray-400">No settings configured</td>', 
         '<td colspan="{{ $canManage ? 4 : 3 }}" class="px-3 py-8 text-center text-gray-400">{{ __(\'ai.no_settings_configured\') }}</td>'),
        
        # Settings actions
        (r'<button wire:click="saveSetting\(\'{{ \$key }}\'\)" class="text-xs text-primary-600 hover:text-primary-800 font-medium">Save</button>', 
         '<button wire:click="saveSetting(\'{{ $key }}\')\" class="text-xs text-primary-600 hover:text-primary-800 font-medium">{{ __(\'ai.save\') }}</button>'),
        (r'<button wire:click="deleteSetting\(\'{{ \$key }}\'\)" wire:confirm="Delete setting \'{{ \$key }}\'\?" class="text-xs text-danger-600 hover:text-danger-800 font-medium">Delete</button>', 
         '<button wire:click="deleteSetting(\'{{ $key }}\')\" wire:confirm="Delete setting \'{{ $key }}\'?" class="text-xs text-danger-600 hover:text-danger-800 font-medium">{{ __(\'ai.delete\') }}</button>'),
        (r'<p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Add New Setting</p>', 
         '<p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">{{ __(\'ai.add_new_setting\') }}</p>'),
        (r'placeholder="e.g. margin_percentage"', r'placeholder="e.g. margin_percentage"'),
        
        # Chats KPIs
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Total Chats</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.total_chats\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Today\'s Chats</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.todays_chats\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Total Messages</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.total_messages\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Avg Messages/Chat</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.avg_messages_per_chat\') }}</p>'),
        
        # Chats display
        (r'<x-filament::section heading="Chats">', 
         '<x-filament::section heading="{{ __(\'ai.chats\') }}">'),
        (r'placeholder="From"', r'placeholder="{{ __(\'ai.from\') }}"'),
        (r'placeholder="To"', r'placeholder="{{ __(\'ai.to\') }}"'),
        (r'<p class="px-3 py-8 text-center text-sm text-gray-400">No chats found</p>', 
         '<p class="px-3 py-8 text-center text-sm text-gray-400">{{ __(\'ai.no_chats_found\') }}</p>'),
        (r'heading="{{ \$selectedChat \? \(\$selectedChat->title \?\? \'Chat Detail\'\) : \'Select a chat\' }}"', 
         'heading="{{ $selectedChat ? ($selectedChat->title ?? __(\'ai.chat_detail\')) : __(\'ai.select_a_chat\') }}"'),
        (r'<p class="text-center text-sm text-gray-400 py-12">Select a chat from the list to view messages</p>', 
         '<p class="text-center text-sm text-gray-400 py-12">{{ __(\'ai.select_chat_list\') }}</p>'),
        
        # Chat messages
        (r'<span class="text-xs font-medium text-gray-500">{{ ucfirst\(\$msg->role\) }}</span>', 
         '<span class="text-xs font-medium text-gray-500">{{ __(\'ai.\' . $msg->role) }}</span>'),
        (r'{{ \$chat->messages_count !== 1 \? \'s\' : \'\' }}', 
         '{{ $chat->messages_count !== 1 ? __(\'ai.msgs\') : __(\'ai.msg\') }}'),
        (r'<p class="text-xs text-gray-400 mt-1">{{ number_format\(\(\$msg->input_tokens \?\? 0\) \+ \(\$msg->output_tokens \?\? 0\)\) }} tokens</p>', 
         '<p class="text-xs text-gray-400 mt-1">{{ number_format(($msg->input_tokens ?? 0) + ($msg->output_tokens ?? 0)) }} {{ __(\'ai.tokens\') }}</p>'),
        
        # Store Intelligence KPIs
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">AI-Active Stores</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.ai_active_stores\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Total Requests</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.total_requests\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Total Raw Cost</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.total_raw_cost\') }}</p>'),
        (r'<p class="text-sm text-gray-500 dark:text-gray-400">Total Billed</p>', 
         '<p class="text-sm text-gray-500 dark:text-gray-400">{{ __(\'ai.total_billed\') }}</p>'),
        
        # Store search
        (r'placeholder="Search stores by name, Arabic name, or slug\.\.\."', 
         'placeholder="{{ __(\'ai.search_stores\') }}"'),
        
        # Store table heading
        (r'<x-filament::section heading="Stores with AI Activity" class="mt-4">', 
         '<x-filament::section heading="{{ __(\'ai.stores_with_ai_activity\') }}" class="mt-4">'),
        (r'<option value="7">Last 7 days</option>', 
         '<option value="7">{{ __(\'ai.last_7_days\') }}</option>'),
        (r'<option value="14">Last 14 days</option>', 
         '<option value="14">{{ __(\'ai.last_14_days\') }}</option>'),
        (r'<option value="30">Last 30 days</option>', 
         '<option value="30">{{ __(\'ai.last_30_days\') }}</option>'),
        (r'<option value="60">Last 60 days</option>', 
         '<option value="60">{{ __(\'ai.last_60_days\') }}</option>'),
        (r'<option value="90">Last 90 days</option>', 
         '<option value="90">{{ __(\'ai.last_90_days\') }}</option>'),
        (r'<option value="365">Last year</option>', 
         '<option value="365">{{ __(\'ai.last_year\') }}</option>'),
        
        # Store detail view
        (r'<button.*?class="flex items-center gap-1 text-sm text-gray-500.*?">.*?\n.*?<x-heroicon-o-arrow-left class="w-4 h-4" />\n.*?Back to Stores\n.*?</button>', 
         '<button wire:click="clearStore" class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">\n                <x-heroicon-o-arrow-left class="w-4 h-4" />\n                {{ __(\'ai.back_to_stores\') }}\n            </button>'),
        (r'<span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">AI Enabled</span>', 
         '<span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __(\'ai.ai_enabled\') }}</span>'),
        (r'<span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">AI Disabled</span>', 
         '<span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __(\'ai.ai_disabled\') }}</span>'),
        
        # Store detail tabs
        (r"'overview' => 'Overview', 'features' => 'Features', 'billing' => 'Billing', 'trends' => 'Trends', 'chats' => 'Chats', 'logs' => 'Logs'",
         "'overview' => __(\'ai.tabs.overview\'), 'features' => __(\'ai.tabs.features\'), 'billing' => __(\'ai.tabs.billing\'), 'trends' => __(\'ai.tabs.trends\'), 'chats' => __(\'ai.tabs.chats\'), 'logs' => __(\'ai.tabs.logs\')"),
    ]
    
    for blade_file in blade_files:
        filepath = BLADE_DIR / blade_file
        if not filepath.exists():
            print(f"⚠ File not found: {filepath}")
            continue
        
        content = filepath.read_text()
        original_content = content
        
        for pattern, replacement in replacements:
            content = re.sub(pattern, replacement, content, flags=re.MULTILINE | re.DOTALL)
        
        if content != original_content:
            filepath.write_text(content)
            print(f"✓ Updated {blade_file}")

def replace_in_php_files():
    """Replace hardcoded strings in PHP files"""
    php_replacements = [
        # Dashboard
        (r"return 'Wameed AI Dashboard';", "return __(\'ai.wameed_ai_dashboard_title\');"),
        
        # Billing page
        (r"return 'AI Billing';", "return __(\'ai.ai_billing_title\');"),
        
        # Chats page
        (r"return 'AI Chat Analytics';", "return __(\'ai.ai_chat_analytics_title\');"),
    ]
    
    php_files = [
        "WameedAIDashboard.php",
        "WameedAIBilling.php",
        "WameedAIChats.php",
        "WameedAIStoreIntelligence.php",
    ]
    
    for php_file in php_files:
        filepath = PAGES_DIR / php_file
        if not filepath.exists():
            print(f"⚠ File not found: {filepath}")
            continue
        
        content = filepath.read_text()
        original_content = content
        
        for pattern, replacement in php_replacements:
            content = re.sub(pattern, replacement, content)
        
        if content != original_content:
            filepath.write_text(content)
            print(f"✓ Updated {php_file}")

if __name__ == "__main__":
    print("Starting translation setup...\n")
    print("Step 1: Creating language files...")
    create_language_files()
    print("\nStep 2: Replacing hardcoded strings in blade files...")
    replace_in_blade_files()
    print("\nStep 3: Replacing hardcoded strings in PHP files...")
    replace_in_php_files()
    print("\n✅ Translation setup complete!")
    print("\nNext steps:")
    print("1. Review the updated blade files: ensure all translations are applied")
    print("2. Run: php artisan cache:clear")
    print("3. Test the admin panel with different locales")
