<?php

return [
    // Navigation
    'nav_group'         => 'الفواتير والمدفوعات',
    'nav_label'         => 'مدفوعات المزودين',
    'model_label'       => 'دفعة مزود',
    'plural_model_label'=> 'مدفوعات المزودين',

    // Sections
    'section_payment_details'       => 'تفاصيل الدفع',
    'section_payment_details_desc'  => 'معلومات الدفع الأساسية.',
    'section_amounts'               => 'المبالغ',
    'section_gateway_response'      => 'استجابة بوابة الدفع',
    'section_tracking'              => 'التتبع والحالة',
    'section_refund'                => 'معلومات الاسترداد',
    'section_email_logs'            => 'سجل البريد الإلكتروني',
    'section_notes'                 => 'ملاحظات',

    // Fields
    'field_organization'        => 'المنظمة',
    'field_purpose'             => 'الغرض',
    'field_purpose_label'       => 'وصف الغرض',
    'field_amount'              => 'المبلغ',
    'field_tax'                 => 'الضريبة',
    'field_total'               => 'المبلغ الإجمالي',
    'field_currency'            => 'العملة',
    'field_status'              => 'الحالة',
    'field_gateway'             => 'بوابة الدفع',
    'field_tran_ref'            => 'مرجع المعاملة',
    'field_tran_type'           => 'نوع المعاملة',
    'field_response_status'     => 'حالة الاستجابة',
    'field_response_code'       => 'رمز الاستجابة',
    'field_response_message'    => 'رسالة الاستجابة',
    'field_card_type'           => 'نوع البطاقة',
    'field_card_scheme'         => 'مخطط البطاقة',
    'field_payment_description' => 'وصف الدفع',
    'field_email_sent'          => 'تم إرسال البريد',
    'field_email_sent_at'       => 'تاريخ إرسال البريد',
    'field_email_error'         => 'خطأ البريد',
    'field_invoice_generated'   => 'تم إنشاء الفاتورة',
    'field_invoice_generated_at'=> 'تاريخ إنشاء الفاتورة',
    'field_ipn_received'        => 'تم استلام الإشعار',
    'field_ipn_received_at'     => 'تاريخ استلام الإشعار',
    'field_notes'               => 'ملاحظات',
    'field_refund_amount'       => 'مبلغ الاسترداد',
    'field_refund_reason'       => 'سبب الاسترداد',
    'field_refund_tran_ref'     => 'مرجع معاملة الاسترداد',
    'field_refunded_at'         => 'تاريخ الاسترداد',

    // Table columns
    'col_cart_id'       => 'معرف السلة',
    'col_organization'  => 'المنظمة',
    'col_purpose'       => 'الغرض',
    'col_purpose_label' => 'وصف الغرض',
    'col_total'         => 'الإجمالي',
    'col_status'        => 'الحالة',
    'col_gateway'       => 'البوابة',
    'col_tran_ref'      => 'مرجع المعاملة',
    'col_card'          => 'البطاقة',
    'col_email'         => 'البريد',
    'col_invoice'       => 'الفاتورة',
    'col_ipn'           => 'الإشعار',
    'col_date'          => 'التاريخ',

    // Filters
    'filter_status'             => 'الحالة',
    'filter_purpose'            => 'الغرض',
    'filter_email_sent'         => 'تم إرسال البريد',
    'filter_invoice_generated'  => 'تم إنشاء الفاتورة',
    'filter_from'               => 'من تاريخ',
    'filter_until'              => 'إلى تاريخ',

    // Actions
    'action_resend_email'   => 'إعادة إرسال البريد',
    'action_query_gateway'  => 'استعلام البوابة',
    'action_refund'         => 'معالجة الاسترداد',

    // Notifications
    'email_resent_success'      => 'تم إعادة إرسال بريد التأكيد بنجاح.',
    'email_resent_failed'       => 'فشل إعادة إرسال بريد التأكيد.',
    'gateway_query_success'     => 'تم تحديث بيانات معاملة البوابة.',
    'gateway_query_failed'      => 'فشل استعلام البوابة. تحقق من السجلات.',
    'refund_confirm_desc'       => 'سيتم معالجة الاسترداد عبر بيتابز. لا يمكن التراجع عن هذا الإجراء.',
    'refund_success'            => 'تم معالجة الاسترداد بنجاح.',

    // Stats
    'stat_total_revenue'        => 'إجمالي الإيرادات',
    'stat_total_revenue_desc'   => 'جميع المدفوعات المكتملة',
    'stat_pending'              => 'قيد الانتظار',
    'stat_pending_desc'         => 'في انتظار الدفع',
    'stat_completed_today'      => 'مكتملة اليوم',
    'stat_completed_today_desc' => 'المدفوعات المكتملة اليوم',
    'stat_failed'               => 'فاشلة',
    'stat_failed_desc'          => 'المدفوعات الفاشلة',
    'stat_refunded'             => 'مستردة',
    'stat_refunded_desc'        => 'إجمالي المبالغ المستردة',

    // Misc
    'no_error'  => 'لا أخطاء',
    'no_notes'  => 'لا ملاحظات.',

    // Email log table
    'email_log_type'        => 'النوع',
    'email_log_recipient'   => 'المستلم',
    'email_log_subject'     => 'الموضوع',
    'email_log_status'      => 'الحالة',
    'email_log_date'        => 'التاريخ',

    // Enums - Status
    'status_pending'    => 'قيد الانتظار',
    'status_processing' => 'قيد المعالجة',
    'status_completed'  => 'مكتملة',
    'status_failed'     => 'فاشلة',
    'status_refunded'   => 'مستردة',
    'status_voided'     => 'ملغية',

    // Enums - Purpose
    'purpose_subscription'       => 'اشتراك',
    'purpose_plan_addon'         => 'إضافة للخطة',
    'purpose_ai_billing'         => 'فوترة الذكاء الاصطناعي',
    'purpose_hardware'           => 'أجهزة',
    'purpose_implementation_fee' => 'رسوم التطبيق',
    'purpose_marketplace_purchase' => 'شراء من المتجر',
    'purpose_other'              => 'أخرى',

    // Enums - Email Type
    'email_type_confirmation' => 'تأكيد الدفع',
    'email_type_invoice'      => 'الفاتورة',
    'email_type_failed'       => 'فشل الدفع',
    'email_type_refund'       => 'تأكيد الاسترداد',

    // API Messages
    'api_payment_initiated'     => 'تم بدء الدفع بنجاح.',
    'api_payment_not_found'     => 'الدفعة غير موجودة.',
    'api_ipn_processed'         => 'تم معالجة الإشعار.',
    'api_ipn_invalid_signature' => 'توقيع الإشعار غير صالح.',
    'api_return_verified'       => 'تم التحقق من الدفع.',
    'api_email_resent'          => 'تم إعادة إرسال البريد بنجاح.',
    'api_email_resend_failed'   => 'فشل إعادة إرسال البريد.',
    'api_refund_initiated'      => 'تم بدء الاسترداد.',

    // Email templates
    'email_payment_confirmation_subject' => 'تأكيد الدفع - :cart_id',
    'email_invoice_subject'              => 'الفاتورة - :invoice_number',
    'email_payment_failed_subject'       => 'فشل الدفع - :cart_id',
    'email_refund_subject'               => 'تأكيد الاسترداد - :cart_id',
];
