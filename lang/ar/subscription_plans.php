<?php

return [

    // ── Navigation ────────────────────────────────────────────────────────────
    'nav_label'             => 'خطط الاشتراك',
    'nav_group'             => 'الفوترة والاشتراكات',

    // ── Resource Labels ───────────────────────────────────────────────────────
    'model_label'           => 'خطة اشتراك',
    'plural_model_label'    => 'خطط الاشتراك',

    // ── Tabs ──────────────────────────────────────────────────────────────────
    'tab_general'           => 'عام',
    'tab_pricing'           => 'التسعير',
    'tab_features'          => 'الميزات',
    'tab_limits'            => 'الحدود',

    // ── General Tab ───────────────────────────────────────────────────────────
    'section_plan_details'              => 'تفاصيل الخطة',
    'section_plan_details_desc'         => 'معلومات الخطة الأساسية المرئية للمشتركين',
    'section_display_settings'          => 'إعدادات العرض',
    'field_name_en'                     => 'اسم الخطة (إنجليزي)',
    'field_name_ar'                     => 'اسم الخطة (عربي)',
    'field_slug'                        => 'المعرّف (Slug)',
    'field_description_en'              => 'الوصف (إنجليزي)',
    'field_description_ar'              => 'الوصف (عربي)',
    'field_is_active'                   => 'نشطة',
    'field_is_active_helper'            => 'تظهر الخطط النشطة فقط للمشتركين',
    'field_is_highlighted'              => 'مميزة / موصى بها',
    'field_is_highlighted_helper'       => 'إبراز هذه الخطة في صفحة التسعير',
    'field_sort_order'                  => 'الترتيب',
    'field_sort_order_helper'           => 'الأرقام الأصغر تظهر أولاً',

    // ── Pricing Tab ───────────────────────────────────────────────────────────
    'section_pricing'                   => 'تسعير الاشتراك',
    'section_pricing_desc'              => 'حدد أسعار الاشتراك الشهري والسنوي بالريال السعودي',
    'section_trial'                     => 'الفترة التجريبية وفترة السماح',
    'section_trial_desc'                => 'تكوين الفترات التجريبية وفترات السماح لهذه الخطة',
    'field_monthly_price'               => 'السعر الشهري (ر.س)',
    'field_annual_price'                => 'السعر السنوي (ر.س)',
    'field_annual_price_helper'         => 'عادةً خصم ~17% مقارنة بالاشتراك الشهري',
    'field_trial_days'                  => 'الفترة التجريبية (أيام)',
    'field_trial_days_helper'           => '0 = بدون فترة تجريبية',
    'field_grace_period_days'           => 'فترة السماح (أيام)',
    'field_grace_period_days_helper'    => 'أيام بعد فشل الدفع قبل تعليق الحساب',

    // ── Features Tab ─────────────────────────────────────────────────────────
    'section_feature_toggles'           => 'تفعيل الميزات',
    'section_feature_toggles_desc'      => 'قم بتفعيل أو تعطيل الميزات لهذه الخطة. كل مفتاح ميزة يرتبط بوحدة في التطبيق.',
    'field_feature_key'                 => 'مفتاح الميزة',
    'field_feature_name_en'             => 'اسم الميزة (إنجليزي)',
    'field_feature_name_ar'             => 'اسم الميزة (عربي)',
    'field_is_enabled'                  => 'مفعّلة',
    'action_add_feature'                => 'إضافة ميزة',
    'new_feature_label'                 => 'ميزة جديدة',

    // Feature key option labels
    'feature_pos'                       => 'نقطة البيع (POS)',
    'feature_zatca_phase2'              => 'زاتكا المرحلة الثانية',
    'feature_inventory'                 => 'إدارة المخزون',
    'feature_reports_basic'             => 'التقارير الأساسية',
    'feature_barcode_scanning'          => 'مسح الباركود',
    'feature_cash_drawer'               => 'درج النقد',
    'feature_customer_display'          => 'شاشة العميل',
    'feature_receipt_printing'          => 'طباعة الإيصالات',
    'feature_offline_mode'              => 'وضع عدم الاتصال',
    'feature_mada_payments'             => 'مدفوعات مدى والبطاقات',
    'feature_reports_advanced'          => 'التحليلات المتقدمة',
    'feature_multi_branch'              => 'متعدد الفروع',
    'feature_delivery_integration'      => 'تكامل التوصيل',
    'feature_customer_loyalty'          => 'ولاء العملاء',
    'feature_api_access'                => 'وصول API',
    'feature_white_label'               => 'العلامة التجارية الخاصة',
    'feature_priority_support'          => 'الدعم الأولوي',
    'feature_dedicated_manager'         => 'مدير مخصص',
    'feature_custom_integrations'       => 'تكاملات مخصصة',
    'feature_sla_guarantee'             => 'ضمان مستوى الخدمة',

    // AI & Tools features
    'feature_wameed_ai'                 => 'وميض الذكاء الاصطناعي',
    'feature_cashier_gamification'      => 'ألعاب تحفيز الكاشير',
    'feature_pos_customization'         => 'تخصيص نقطة البيع',
    'feature_companion_app'             => 'التطبيق المصاحب',
    'feature_installments'              => 'الدفع بالتقسيط',
    'feature_accounting'                => 'المحاسبة',

    // Catalog & product features
    'feature_customer_management'       => 'إدارة العملاء',
    'feature_product_modifiers'         => 'إضافات المنتج',
    'feature_supplier_management'       => 'إدارة الموردين',
    'feature_product_variants'          => 'متغيرات المنتج',
    'feature_combo_products'            => 'منتجات الكومبو',
    'feature_bulk_import'               => 'استيراد CSV الجماعي',
    'feature_barcode_label_printing'    => 'طباعة ملصقات الباركود',

    // Promotions & marketing features
    'feature_promotions_coupons'        => 'العروض والكوبونات',
    'feature_promotions_advanced'       => 'عروض متقدمة (اشتر واحداً واحصل على الآخر، الحزم، الساعة السعيدة)',

    // Industry vertical features
    'feature_industry_restaurant'       => 'ميزات المطاعم',
    'feature_industry_bakery'           => 'ميزات المخابز',
    'feature_industry_pharmacy'         => 'ميزات الصيدليات',
    'feature_industry_electronics'      => 'ميزات الإلكترونيات',
    'feature_industry_florist'          => 'ميزات بائع الزهور',
    'feature_industry_jewelry'          => 'ميزات المجوهرات',

    // ── Limits Tab ───────────────────────────────────────────────────────────
    'section_plan_limits'               => 'حدود الخطة',
    'section_plan_limits_desc'          => 'حدد قيوداً صارمة لكل مورد. سيُطلب من المتاجر التي تتجاوز الحدود الترقية.',
    'field_limit_key'                   => 'المورد',
    'field_limit_value'                 => 'الحد الأقصى',
    'field_limit_value_helper'          => '0 = معطّل، 1- = غير محدود',
    'field_overage_price'               => 'سعر الزيادة (ر.س)',
    'field_overage_price_helper'        => 'رسوم لكل وحدة إضافية فوق الحد',
    'action_add_limit'                  => 'إضافة حد',
    'new_limit_label'                   => 'حد جديد',

    // Limit key option labels
    'limit_products'                    => 'المنتجات',
    'limit_staff_members'               => 'أعضاء الفريق',
    'limit_stores'                      => 'المتاجر / الفروع',
    'limit_transactions_month'          => 'المعاملات / الشهر',
    'limit_customers'                   => 'العملاء',
    'limit_categories'                  => 'التصنيفات',
    'limit_promotions'                  => 'العروض النشطة',
    'limit_custom_roles'                => 'الأدوار المخصصة',
    'limit_api_calls_day'               => 'طلبات API / اليوم',
    'limit_storage_mb'                  => 'التخزين (MB)',
    'limit_reports'                     => 'التقارير المحفوظة',
    'limit_cashier_terminals'           => 'أجهزة الكاشير',
    'limit_branches'                    => 'الفروع',
    'limit_transactions_per_month'      => 'المعاملات / الشهر',
    'limit_pdf_reports_per_month'       => 'تقارير PDF / الشهر',

    // ── Table Columns ─────────────────────────────────────────────────────────
    'col_plan'                          => 'الخطة',
    'col_monthly'                       => 'شهري',
    'col_annual'                        => 'سنوي',
    'col_trial'                         => 'تجريبي',
    'col_subscribers'                   => 'المشتركون',
    'col_features'                      => 'الميزات',
    'col_active'                        => 'نشطة',
    'col_order'                         => 'الترتيب',

    // ── Table Filters ─────────────────────────────────────────────────────────
    'filter_status'                     => 'الحالة',
    'filter_all_plans'                  => 'جميع الخطط',
    'filter_active_only'                => 'النشطة فقط',
    'filter_inactive_only'              => 'غير النشطة فقط',
    'filter_highlighted'                => 'المميزة',
    'filter_all'                        => 'الكل',
    'filter_not_highlighted'            => 'غير المميزة',

    // ── Actions ───────────────────────────────────────────────────────────────
    'action_toggle_active_deactivate'   => 'إلغاء التفعيل',
    'action_toggle_active_activate'     => 'تفعيل',
    'action_duplicate'                  => 'تكرار الخطة',
    'action_duplicate_desc'             => 'سيُنشئ هذا مسودة خطة جديدة بناءً على هذه الخطة، بما في ذلك الميزات والحدود.',

    // ── Infolist ─────────────────────────────────────────────────────────────
    'info_plan_overview'                => 'نظرة عامة على الخطة',
    'info_name_en'                      => 'الاسم (إنجليزي)',
    'info_name_ar'                      => 'الاسم (عربي)',
    'info_feature_key'                  => 'مفتاح الميزة',
    'info_feature_name_en'              => 'الاسم (إنجليزي)',
    'info_feature_name_ar'              => 'الاسم (عربي)',
    'info_is_enabled'                   => 'مفعّلة',
    'info_resource'                     => 'المورد',
    'info_limit'                        => 'الحد الأقصى',
    'info_overage'                      => 'رسوم الزيادة',

    // ── SoftPOS Free Tier ─────────────────────────────────────────────────────
    'section_softpos_free'                       => 'SoftPOS المجاني',
    'section_softpos_free_desc'                  => 'اجعل هذه الخطة مجانية للمتاجر التي تصل إلى حد معاملات SoftPOS',
    'field_softpos_free_eligible'                => 'مؤهل لـ SoftPOS المجاني',
    'field_softpos_free_eligible_helper'         => 'عند التفعيل، ستحصل المتاجر التي تصل إلى حد المعاملات على هذه الخطة مجاناً',
    'field_softpos_free_threshold'               => 'حد المعاملات',
    'field_softpos_free_threshold_helper'        => 'عدد معاملات SoftPOS المطلوبة لفتح المستوى المجاني',
    'field_softpos_free_threshold_period'        => 'فترة الحد',
    'field_softpos_free_threshold_period_helper' => 'الإطار الزمني الذي يجب إتمام المعاملات خلاله',
    'period_monthly'                             => 'شهري',
    'period_quarterly'                           => 'ربع سنوي',
    'period_annually'                            => 'سنوي',
];
