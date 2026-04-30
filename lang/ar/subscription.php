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
    'addon_already_active'                => 'هذه الإضافة مفعّلة بالفعل في متجرك.',
    'addon_activated'                     => 'تم تفعيل الإضافة بنجاح.',
    'addon_already_deactivated'           => 'الإضافة معطلة بالفعل.',
    'addon_removed'                       => 'تمت إزالة الإضافة بنجاح. لن تظهر في فاتورتك القادمة.',

    // ── Discount Codes ────────────────────────────────────────────────────────
    'discount_valid'                      => 'تم تطبيق كود الخصم بنجاح.',
    'discount_invalid'                    => 'كود الخصم غير صالح أو منتهي الصلاحية.',
    'discount_expired'                    => 'وصل كود الخصم إلى الحد الأقصى من الاستخدامات.',
    'discount_not_applicable'             => 'كود الخصم غير قابل للتطبيق على الخطة المحددة.',

    // ── Limit Key Labels (for user-facing messages) ──────────────────────────
    'limit_key_products'                  => 'المنتجات',
    'limit_key_staff_members'             => 'أعضاء الفريق',
    'limit_key_cashier_terminals'         => 'أجهزة الكاشير',
    'limit_key_branches'                  => 'الفروع',
    'limit_key_transactions_per_month'    => 'المعاملات الشهرية',
    'limit_key_storage_mb'                => 'التخزين',
    'limit_key_pdf_reports_per_month'     => 'تقارير PDF الشهرية',

    // ── بريد تذكير الدفع ────────────────────────────────────────
    'email_brand_name'            => 'Wameed POS',
    'email_reminder_title'        => 'تذكير الاشتراك',
    'email_upcoming_heading'      => 'اشتراكك على وشك الانتهاء',
    'email_upcoming_body'         => 'اشتراكك في خطة :plan ينتهي بتاريخ :date. يرجى التجديد لتجنّب انقطاع الخدمة.',
    'email_overdue_heading'       => 'انتهى اشتراكك',
    'email_overdue_body'          => 'انتهى اشتراكك في خطة :plan. يرجى التجديد فوراً لاستعادة الوصول الكامل.',
    'email_trial_heading'         => 'تجربتك المجانية على وشك الانتهاء',
    'email_trial_body'            => 'تجربتك المجانية لخطة :plan تنتهي في :date. اشترك الآن للاحتفاظ بجميع الميزات.',
    'email_label_organization'    => 'المؤسسة',
    'email_label_plan'            => 'الخطة',
    'email_label_expiry_date'     => 'تاريخ الانتهاء',
    'email_footer_rights'         => 'جميع الحقوق محفوظة.',

    // ── صفحة نتيجة الدفع ─────────────────────────────────────────
    'payment_success_title'       => 'تم الدفع بنجاح',
    'payment_success_sub'         => 'تم الدفع بنجاح',
    'payment_pending_title'       => 'الدفع قيد المعالجة',
    'payment_pending_sub'         => 'جاري معالجة الدفع',
    'payment_failed_title'        => 'فشل الدفع',
    'payment_failed_sub'          => 'فشل الدفع',
    'payment_total_amount'        => 'المبلغ الإجمالي',
    'payment_purpose'             => 'الغرض',
    'payment_subtotal'            => 'المبلغ',
    'payment_vat'                 => 'ضريبة القيمة المضافة',
    'payment_reference'           => 'رقم المرجع',
    'payment_order_id'            => 'رقم الطلب',
    'payment_card'                => 'البطاقة',
    'payment_date'                => 'التاريخ',
    'payment_reason'              => 'السبب',
    'payment_not_available'       => 'لا يمكن العثور على معلومات الدفع',
    'payment_footer'              => 'وميض نقاط البيع &nbsp;|&nbsp; شكراً لاستخدامكم خدماتنا',

    // ── فاتورة الاشتراك (PDF) ───────────────────────────────────
    'invoice_status_paid'         => 'مدفوعة',
    'invoice_brand'               => 'Wameed POS',
    'invoice_sub_brand'           => 'فاتورة المنصة',
    'invoice_title'               => 'فاتورة',
    'invoice_from'                => 'من',
    'invoice_bill_to'             => 'إلى',
    'invoice_issue_date'          => 'تاريخ الإصدار',
    'invoice_due_date'            => 'تاريخ الاستحقاق',
    'invoice_col_description'     => 'الوصف',
    'invoice_col_qty'             => 'الكمية',
    'invoice_col_unit_price'      => 'سعر الوحدة (ر.س)',
    'invoice_col_total'           => 'الإجمالي (ر.س)',
    'invoice_items'               => 'عناصر الفاتورة',
    'invoice_subtotal'            => 'المجموع الجزئي',
    'invoice_vat'                 => 'ضريبة القيمة المضافة (15%)',
    'invoice_total_due'           => 'إجمالي المستحق',
    'invoice_payment_details'     => 'تفاصيل الدفع',
    'invoice_payment_method'      => 'طريقة الدفع',
    'invoice_transaction_ref'     => 'مرجع المعاملة',
    'invoice_payment_date'        => 'تاريخ الدفع',
    'invoice_footer_thanks'       => 'شكراً لتعاملكم مع Wameed POS.',
    'invoice_footer_billing'      => 'للاستفسارات تواصل مع billing@wameedpos.sa',
    'invoice_footer_generated'    => 'هذه فاتورة مولّدة آلياً ولا تحتاج إلى توقيع.',

    // ── Issuer info (shown on invoice) ────────────────────────────
    'issuer_company_name'         => 'شركة وامد للتقنية',
];
