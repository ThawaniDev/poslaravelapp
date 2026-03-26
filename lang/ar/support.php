<?php

return [
    // ═══════════════════════════════════════════════════════════
    //  Navigation & Resource Labels
    // ═══════════════════════════════════════════════════════════
    'nav_tickets'          => 'تذاكر الدعم',
    'nav_canned_responses' => 'الردود الجاهزة',
    'nav_knowledge_base'   => 'قاعدة المعرفة',
    'ticket'               => 'تذكرة دعم',
    'tickets'              => 'تذاكر الدعم',
    'canned_response'      => 'رد جاهز',
    'canned_responses'     => 'الردود الجاهزة',
    'kb_article'           => 'مقال',
    'kb_articles'          => 'مقالات قاعدة المعرفة',
    'draft_articles'       => 'مقالات مسودة',

    // ═══════════════════════════════════════════════════════════
    //  Ticket Status
    // ═══════════════════════════════════════════════════════════
    'status_open'        => 'مفتوحة',
    'status_in_progress' => 'قيد المعالجة',
    'status_resolved'    => 'تم الحل',
    'status_closed'      => 'مغلقة',

    // ═══════════════════════════════════════════════════════════
    //  Ticket Priority
    // ═══════════════════════════════════════════════════════════
    'priority_low'      => 'منخفضة',
    'priority_medium'   => 'متوسطة',
    'priority_high'     => 'عالية',
    'priority_critical' => 'حرجة',

    // ═══════════════════════════════════════════════════════════
    //  Ticket Category
    // ═══════════════════════════════════════════════════════════
    'category_billing'         => 'الفواتير',
    'category_technical'       => 'تقنية',
    'category_zatca'           => 'زاتكا',
    'category_feature_request' => 'طلب ميزة',
    'category_general'         => 'عام',
    'category_hardware'        => 'الأجهزة',

    // ═══════════════════════════════════════════════════════════
    //  Sender Type
    // ═══════════════════════════════════════════════════════════
    'sender_provider' => 'مزود الخدمة',
    'sender_admin'    => 'المشرف',

    // ═══════════════════════════════════════════════════════════
    //  Knowledge Base Category
    // ═══════════════════════════════════════════════════════════
    'kb_cat_getting_started'  => 'البدء',
    'kb_cat_pos_usage'        => 'استخدام نقطة البيع',
    'kb_cat_inventory'        => 'المخزون',
    'kb_cat_delivery'         => 'التوصيل',
    'kb_cat_billing'          => 'الفواتير',
    'kb_cat_troubleshooting'  => 'استكشاف الأخطاء',

    // ═══════════════════════════════════════════════════════════
    //  Form / Table Fields
    // ═══════════════════════════════════════════════════════════
    'ticket_number'      => 'رقم التذكرة',
    'subject'            => 'الموضوع',
    'description'        => 'الوصف',
    'category'           => 'الفئة',
    'priority'           => 'الأولوية',
    'status'             => 'الحالة',
    'organization'       => 'المنظمة',
    'store'              => 'المتجر',
    'assigned_to'        => 'مسند إلى',
    'agent'              => 'الوكيل',
    'sla_deadline'       => 'موعد SLA',
    'first_response_at'  => 'أول رد',
    'resolved_at'        => 'تاريخ الحل',
    'closed_at'          => 'تاريخ الإغلاق',
    'created_at'         => 'تاريخ الإنشاء',
    'updated_at'         => 'تاريخ التحديث',
    'messages_count'     => 'الرسائل',
    'sla_status'         => 'حالة SLA',
    'title'              => 'العنوان',
    'title_en'           => 'العنوان (EN)',
    'title_ar'           => 'العنوان (AR)',
    'body_en'            => 'المحتوى (EN)',
    'body_ar'            => 'المحتوى (AR)',
    'shortcut'           => 'الاختصار',
    'shortcut_help'      => 'اكتب / متبوعًا بالاختصار لإدراج هذا الرد بسرعة.',
    'is_active'          => 'نشط',
    'is_published'       => 'منشور',
    'slug'               => 'المعرّف',
    'slug_help'          => 'معرّف صديق لعنوان URL. يتم إنشاؤه تلقائيًا من العنوان.',
    'sort_order'         => 'ترتيب العرض',
    'created_by'         => 'أنشأ بواسطة',

    // ═══════════════════════════════════════════════════════════
    //  Form Sections
    // ═══════════════════════════════════════════════════════════
    'ticket_details'      => 'تفاصيل التذكرة',
    'ticket_description'  => 'الوصف',
    'assignment'          => 'التعيين',
    'response_details'    => 'تفاصيل الرد',
    'response_body'       => 'محتوى الرد',
    'article_details'     => 'تفاصيل المقال',
    'article_body'        => 'محتوى المقال',

    // ═══════════════════════════════════════════════════════════
    //  Infolist Sections
    // ═══════════════════════════════════════════════════════════
    'ticket_information'   => 'معلومات التذكرة',
    'conversation'         => 'المحادثة',
    'organization_info'    => 'معلومات المنظمة',
    'sla_tracking'         => 'تتبع SLA',
    'sla_met'              => 'تم الالتزام',
    'sla_breached'         => 'تم التجاوز',
    'sla_on_track'         => 'في المسار',
    'sla_none'             => 'غير محدد',
    'internal_note'        => 'ملاحظة داخلية',

    // ═══════════════════════════════════════════════════════════
    //  Actions
    // ═══════════════════════════════════════════════════════════
    'assign'              => 'تعيين',
    'assign_agent'        => 'تعيين وكيل',
    'resolve'             => 'حل',
    'resolve_confirm'     => 'هل تريد تحديد هذه التذكرة كمحلولة؟',
    'close_ticket'        => 'إغلاق',
    'close_confirm'       => 'هل تريد إغلاق هذه التذكرة؟ لا يمكن التراجع.',
    'reply'               => 'رد',
    'reply_message'       => 'ردك',
    'is_internal'         => 'ملاحظة داخلية',
    'escalate'            => 'تصعيد',
    'escalate_confirm'    => 'تعيين الأولوية كحرجة وإضافة ملاحظة داخلية؟',
    'change_priority'     => 'تغيير الأولوية',
    'new_priority'        => 'الأولوية الجديدة',
    'activate'            => 'تفعيل',
    'deactivate'          => 'إلغاء التفعيل',
    'activated'           => 'تم التفعيل بنجاح.',
    'deactivated'         => 'تم إلغاء التفعيل بنجاح.',
    'publish'             => 'نشر',
    'unpublish'           => 'إلغاء النشر',

    // ═══════════════════════════════════════════════════════════
    //  Notifications
    // ═══════════════════════════════════════════════════════════
    'reply_sent'          => 'تم إرسال الرد بنجاح.',
    'ticket_escalated'    => 'تم تصعيد التذكرة إلى حرجة.',
    'ticket_resolved'     => 'تم حل التذكرة.',
    'ticket_closed'       => 'تم إغلاق التذكرة.',
    'ticket_assigned'     => 'تم تعيين التذكرة بنجاح.',
    'tickets_assigned'    => 'تم تعيين التذاكر بنجاح.',
    'tickets_closed'      => 'تم إغلاق التذاكر بنجاح.',
    'priority_changed'    => 'تم تغيير الأولوية بنجاح.',
    'status_changed'      => 'تم تغيير الحالة بنجاح.',
    'ticket_updated'      => 'تم تحديث التذكرة بنجاح.',

    // ═══════════════════════════════════════════════════════════
    //  Stats / Analytics
    // ═══════════════════════════════════════════════════════════
    'stat_total'           => 'إجمالي التذاكر',
    'stat_all_tickets'     => 'عدد التذاكر الإجمالي',
    'stat_open'            => 'مفتوحة',
    'stat_awaiting_response' => 'بانتظار الرد',
    'stat_in_progress'     => 'قيد المعالجة',
    'stat_being_handled'   => 'يتم التعامل معها',
    'stat_resolved_today'  => 'تم الحل اليوم',
    'stat_closed_today'    => 'أُغلقت اليوم',
    'stat_sla_breached'    => 'تجاوز SLA',
    'stat_past_deadline'   => 'تجاوز موعد SLA',
    'stat_critical'        => 'حرجة',
    'stat_critical_tickets' => 'تذاكر حرجة مفتوحة',
    'stat_unassigned'      => 'غير مُسندة',
    'stat_needs_assignment' => 'تحتاج تعيين',

    'chart_ticket_volume'  => 'حجم التذاكر (30 يوم)',
    'chart_created'        => 'تم الإنشاء',
    'chart_resolved'       => 'تم الحل',
    'chart_by_category'    => 'التذاكر حسب الفئة',

    // ═══════════════════════════════════════════════════════════
    //  API Messages
    // ═══════════════════════════════════════════════════════════
    'tickets_retrieved'          => 'تم استرجاع تذاكر الدعم.',
    'ticket_retrieved'           => 'تم استرجاع تذكرة الدعم.',
    'ticket_not_found'           => 'التذكرة غير موجودة.',
    'ticket_created'             => 'تم إنشاء تذكرة الدعم.',
    'message_sent'               => 'تم إرسال الرسالة.',
    'messages_retrieved'         => 'تم استرجاع الرسائل.',
    'stats_retrieved'            => 'تم استرجاع إحصائيات الدعم.',
    'canned_responses_retrieved' => 'تم استرجاع الردود الجاهزة.',
    'canned_response_created'    => 'تم إنشاء الرد الجاهز.',
    'canned_response_retrieved'  => 'تم استرجاع الرد الجاهز.',
    'canned_response_not_found'  => 'الرد الجاهز غير موجود.',
    'canned_response_updated'    => 'تم تحديث الرد الجاهز.',
    'canned_response_deleted'    => 'تم حذف الرد الجاهز.',
    'canned_response_toggled'    => 'تم تبديل حالة الرد الجاهز.',
    'kb_articles_retrieved'      => 'تم استرجاع مقالات قاعدة المعرفة.',
    'kb_article_retrieved'       => 'تم استرجاع مقال قاعدة المعرفة.',
    'kb_article_not_found'       => 'مقال قاعدة المعرفة غير موجود.',
    'kb_article_created'         => 'تم إنشاء مقال قاعدة المعرفة.',
    'kb_article_updated'         => 'تم تحديث مقال قاعدة المعرفة.',
    'kb_article_deleted'         => 'تم حذف مقال قاعدة المعرفة.',
];
