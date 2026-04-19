<?php

return [

    // ── Enforcement ───────────────────────────────────────────────────────────
    'organization_required'               => 'سياق المنظمة مطلوب.',
    'no_organization'                     => 'لم يتم تعيين منظمة لهذا المستخدم.',
    'no_store'                            => 'لم يتم تعيين متجر لهذا المستخدم.',

    'limit_exceeded'                      => 'لقد وصلت إلى الحد الأقصى لـ :resource. يرجى ترقية خطتك.',
    'plan_limit_exceeded'                 => 'لقد وصلت إلى الحد الأقصى لهذا المورد في خطتك. يرجى ترقية خطتك لزيادة الحصة.',

    // ── Subscription Status ───────────────────────────────────────────────────
    'no_active_subscription'              => 'لا يوجد اشتراك نشط.',
    'subscribed_successfully'             => 'تم الاشتراك بنجاح.',
    'plan_not_found'                      => 'الخطة المحددة غير موجودة.',
    'plan_changed'                        => 'تم تغيير الخطة بنجاح.',
    'plan_or_subscription_not_found'      => 'الاشتراك النشط أو الخطة المستهدفة غير موجودة.',
    'subscription_cancelled'              => 'تم إلغاء الاشتراك.',
    'no_active_to_cancel'                 => 'لا يوجد اشتراك نشط لإلغائه.',
    'subscription_resumed'                => 'تم استئناف الاشتراك.',
    'no_cancelled_to_resume'              => 'لا يوجد اشتراك ملغى لاستئنافه.',

    // ── SoftPOS ───────────────────────────────────────────────────────────────
    'softpos_not_available'               => 'مستوى SoftPOS المجاني غير متوفر لخطتك الحالية.',
    'softpos_transaction_recorded'        => 'تم تسجيل معاملة SoftPOS.',
    'store_not_in_organization'           => 'المتجر لا ينتمي إلى منظمتك.',

    // ── Add-ons ───────────────────────────────────────────────────────────────
    'addon_not_found'                     => 'الإضافة غير موجودة لهذا المتجر.',
    'addon_already_deactivated'           => 'الإضافة معطلة بالفعل.',
    'addon_removed'                       => 'تمت إزالة الإضافة بنجاح. لن تظهر في فاتورتك القادمة.',

    // ── Limit Key Labels (for user-facing messages) ──────────────────────────
    'limit_key_products'                  => 'المنتجات',
    'limit_key_staff_members'             => 'أعضاء الفريق',
    'limit_key_cashier_terminals'         => 'أجهزة الكاشير',
    'limit_key_branches'                  => 'الفروع',
    'limit_key_transactions_per_month'    => 'المعاملات الشهرية',
    'limit_key_storage_mb'                => 'التخزين',
    'limit_key_pdf_reports_per_month'     => 'تقارير PDF الشهرية',
];
