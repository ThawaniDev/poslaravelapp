<?php

return [
    // CRUD
    'created'     => 'تم إنشاء الجهاز بنجاح.',
    'updated'     => 'تم تحديث الجهاز بنجاح.',
    'deleted'     => 'تم حذف الجهاز بنجاح.',
    'activated'   => 'تم تفعيل الجهاز.',
    'deactivated' => 'تم إلغاء تفعيل الجهاز.',

    // Validation
    'store_required'         => 'المتجر مطلوب.',
    'store_not_found'        => 'المتجر المحدد غير موجود.',
    'name_required'          => 'اسم الجهاز مطلوب.',
    'device_id_required'     => 'معرف الجهاز مطلوب.',
    'device_id_taken'        => 'معرف الجهاز مسجل مسبقاً.',
    'platform_required'      => 'المنصة مطلوبة.',
    'platform_invalid'       => 'منصة غير صالحة. اختر من: Windows, macOS, iOS, Android.',
    'acquirer_source_invalid' => 'مصدر المعالج غير صالح. اختر من: HALA, الراجحي, SNB, Geidea, أخرى.',
    'softpos_status_invalid' => 'حالة SoftPOS غير صالحة. اختر من: معلق, نشط, معلق, معطل.',
    'fee_profile_invalid'    => 'ملف الرسوم غير صالح. اختر من: قياسي, مخصص, ترويجي.',
    'iban_too_long'          => 'رقم الآيبان يجب ألا يتجاوز 34 حرفاً.',

    // SoftPOS
    'softpos_activated'    => 'تم تفعيل SoftPOS بنجاح.',
    'softpos_suspended'    => 'تم تعليق SoftPOS.',
    'softpos_deactivated'  => 'تم إلغاء تفعيل SoftPOS.',
    'softpos_no_tid'       => 'لا يمكن تفعيل SoftPOS: معرف الجهاز (TID) غير مُعد.',
    'softpos_no_acquirer'  => 'لا يمكن تفعيل SoftPOS: مصدر المعالج غير مُعد.',
    'fees_updated'         => 'تم تحديث إعدادات رسوم المعاملات.',

    // Labels
    'terminal'              => 'جهاز',
    'terminals'             => 'الأجهزة',
    'softpos'               => 'SoftPOS',
    'softpos_settings'      => 'إعدادات SoftPOS',
    'nearpay_tid'           => 'معرف جهاز NearPay (TID)',
    'nearpay_mid'           => 'معرف التاجر (MID)',
    'acquirer_source'       => 'مصدر المعالج',
    'acquirer_name'         => 'اسم المعالج',
    'acquirer_reference'    => 'مرجع المعالج',
    'device_model'          => 'طراز الجهاز',
    'os_version'            => 'إصدار النظام',
    'nfc_capable'           => 'يدعم NFC',
    'serial_number'         => 'الرقم التسلسلي',
    'fee_profile'           => 'ملف الرسوم',
    'fee_mada'              => 'رسوم مدى (%)',
    'fee_visa_mc'           => 'رسوم Visa/MC (%)',
    'fee_flat'              => 'رسوم ثابتة لكل معاملة Visa/MC/Amex (ر.س)',
    'fee_flat_helper'       => 'رسوم ثابتة بالريال السعودي تُطبق على كل معاملة Visa أو Mastercard أو Amex.',
    'wameed_margin'        => 'هامش وميض (%)',
    'settlement_cycle'      => 'دورة التسوية',
    'settlement_bank'       => 'بنك التسوية',
    'settlement_iban'       => 'آيبان التسوية',
    'softpos_status'        => 'حالة SoftPOS',
    'admin_notes'           => 'ملاحظات المسؤول',
    'last_transaction_at'   => 'آخر معاملة',
    'softpos_activated_at'  => 'تاريخ تفعيل SoftPOS',

    // Acquirer labels
    'acquirer_hala'        => 'هلا',
    'acquirer_bank_rajhi'  => 'بنك الراجحي',
    'acquirer_bank_snb'    => 'البنك الأهلي السعودي',
    'acquirer_geidea'      => 'Geidea',
    'acquirer_other'       => 'أخرى',

    // Fee profiles
    'fee_standard'     => 'قياسي',
    'fee_custom'       => 'مخصص',
    'fee_promotional'  => 'ترويجي',

    // SoftPOS statuses
    'status_pending'      => 'معلق',
    'status_active'       => 'نشط',
    'status_suspended'    => 'موقوف',
    'status_deactivated'  => 'معطل',

    // Resource actions
    'view_sessions'                  => 'عرض الجلسات',
    'view_transactions'              => 'عرض المعاملات',
    'clone_terminal'                 => 'نسخ جهاز نقطة البيع',
    'clone_device_id_hint'           => 'اتركه فارغًا إذا سيتم تسجيل الجهاز عند أول تشغيل.',
    'cloned'                         => 'تم نسخ جهاز نقطة البيع بنجاح.',
    'bulk_toggle_softpos'            => 'تبديل SoftPOS للمحدد',
    'bulk_toggle_softpos_warning'    => 'سيتم عكس حالة تفعيل SoftPOS لكل جهاز محدد. سيتم تعطيل الأجهزة المفعّل عليها SoftPOS حاليًا.',
];
