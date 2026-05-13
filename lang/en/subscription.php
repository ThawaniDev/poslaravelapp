<?php

return [

    // ── Enforcement ───────────────────────────────────────────────────────────
    'organization_required'               => 'Organization context required.',
    'no_organization'                     => 'No organization assigned to this user.',
    'no_store'                            => 'No store assigned to this user.',

    'limit_exceeded'                      => 'You have reached the limit for :resource. Please upgrade your plan.',
    'plan_limit_exceeded'                 => 'You have reached your plan limit for this resource. Please upgrade your plan to increase your quota.',

    // ── Subscription Status ───────────────────────────────────────────────────
    'no_active_subscription'              => 'No active subscription.',
    'subscribed_successfully'             => 'Subscribed successfully.',
    'plan_not_found'                      => 'Selected plan not found.',
    'plan_changed'                        => 'Plan changed successfully.',
    'plan_or_subscription_not_found'      => 'Active subscription or target plan not found.',
    'subscription_cancelled'              => 'Subscription cancelled.',
    'no_active_to_cancel'                 => 'No active subscription found to cancel.',
    'subscription_resumed'                => 'Subscription resumed.',
    'no_cancelled_to_resume'              => 'No cancelled subscription found to resume.',

    // ── SoftPOS ───────────────────────────────────────────────────────────────
    'softpos_not_available'               => 'SoftPOS free tier is not available for your current plan.',
    'softpos_transaction_recorded'        => 'SoftPOS transaction recorded.',
    'store_not_in_organization'           => 'Store does not belong to your organization.',
    'terminal_not_in_organization'        => 'Terminal does not belong to your organization.',

    // ── Add-ons ───────────────────────────────────────────────────────────────
    'addon_not_found'                     => 'Add-on not found for this store.',
    'addon_already_active'                => 'This add-on is already active for your store.',
    'addon_activated'                     => 'Add-on activated successfully.',
    'addon_already_deactivated'           => 'Add-on is already deactivated.',
    'addon_removed'                       => 'Add-on removed successfully. It will not appear on your next invoice.',

    // ── Discount Codes ────────────────────────────────────────────────────────
    'discount_valid'                      => 'Discount code applied successfully.',
    'discount_invalid'                    => 'Invalid or expired discount code.',
    'discount_expired'                    => 'This discount code has reached its maximum usage limit.',
    'discount_not_applicable'             => 'This discount code is not applicable to the selected plan.',

    // ── Limit Key Labels (for user-facing messages) ──────────────────────────
    'limit_key_products'                  => 'products',
    'limit_key_staff_members'             => 'staff members',
    'limit_key_cashier_terminals'         => 'cashier terminals',
    'limit_key_branches'                  => 'branches',
    'limit_key_transactions_per_month'    => 'monthly transactions',
    'limit_key_storage_mb'                => 'storage',
    'limit_key_pdf_reports_per_month'     => 'monthly PDF reports',

    // ── Payment Reminder Email ──────────────────────────────────────
    'email_brand_name'            => 'Wameed POS',
    'email_reminder_title'        => 'Subscription Reminder',
    'email_upcoming_heading'      => 'Your subscription is expiring soon',
    'email_upcoming_body'         => 'Your :plan plan expires on :date. Please renew to avoid service interruption.',
    'email_overdue_heading'       => 'Your subscription has expired',
    'email_overdue_body'          => 'Your :plan plan expired. Please renew immediately to restore full access.',
    'email_trial_heading'         => 'Your trial is ending soon',
    'email_trial_body'            => 'Your trial for :plan ends on :date. Subscribe now to keep all features.',
    'email_label_organization'    => 'Organization',
    'email_label_plan'            => 'Plan',
    'email_label_expiry_date'     => 'Expiry Date',
    'email_footer_rights'         => 'All rights reserved.',

    // ── Payment Result Page ────────────────────────────────────────
    'payment_success_title'       => 'تم الدفع بنجاح',
    'payment_success_sub'         => 'Payment Successful',
    'payment_pending_title'       => 'الدفع قيد المعالجة',
    'payment_pending_sub'         => 'Payment Processing',
    'payment_failed_title'        => 'فشل الدفع',
    'payment_failed_sub'          => 'Payment Failed',
    'payment_total_amount'        => 'المبلغ الإجمالي',
    'payment_purpose'             => 'الغرض',
    'payment_subtotal'            => 'المبلغ',
    'payment_vat'                 => 'الضريبة',
    'payment_reference'           => 'رقم المرجع',
    'payment_order_id'            => 'رقم الطلب',
    'payment_card'                => 'البطاقة',
    'payment_date'                => 'التاريخ',
    'payment_reason'              => 'السبب',
    'payment_not_available'       => 'لا يمكن العثور على معلومات الدفع',
    'payment_footer'              => 'وميض نقاط البيع &nbsp;|&nbsp; شكراً لاستخدامكم خدماتنا',

    // ── Subscription Invoice (PDF) ─────────────────────────────────
    'invoice_status_paid'         => 'PAID',
    'invoice_brand'               => 'Wameed POS',
    'invoice_sub_brand'           => 'Platform Invoice',
    'invoice_title'               => 'Invoice',
    'invoice_from'                => 'From',
    'invoice_bill_to'             => 'Bill To',
    'invoice_issue_date'          => 'Issue Date',
    'invoice_due_date'            => 'Due Date',
    'invoice_col_description'     => 'Description',
    'invoice_col_qty'             => 'Qty',
    'invoice_col_unit_price'      => 'Unit Price (SAR)',
    'invoice_col_total'           => 'Total (SAR)',
    'invoice_items'               => 'Invoice Items',
    'invoice_subtotal'            => 'Subtotal',
    'invoice_vat'                 => 'VAT (15%)',
    'invoice_total_due'           => 'Total Due',
    'invoice_payment_details'     => 'Payment Details',
    'invoice_payment_method'      => 'Payment Method',
    'invoice_transaction_ref'     => 'Transaction Reference',
    'invoice_payment_date'        => 'Payment Date',
    'invoice_footer_thanks'       => 'Thank you for your business with Wameed POS.',
    'invoice_footer_billing'      => 'For billing inquiries, contact billing@wameedpos.sa',
    'invoice_footer_generated'    => 'This is a computer-generated invoice and does not require a signature.',

    // ── Issuer info (shown on invoice) ────────────────────────────
    'issuer_company_name'         => 'Wameed Technology',
];
