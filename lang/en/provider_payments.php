<?php

return [
    // Navigation
    'nav_group'         => 'Billing & Payments',
    'nav_label'         => 'Provider Payments',
    'model_label'       => 'Provider Payment',
    'plural_model_label'=> 'Provider Payments',

    // Sections
    'section_payment_details'       => 'Payment Details',
    'section_payment_details_desc'  => 'Core payment information.',
    'section_amounts'               => 'Amounts',
    'section_gateway_response'      => 'Gateway Response',
    'section_tracking'              => 'Tracking & Status',
    'section_refund'                => 'Refund Information',
    'section_email_logs'            => 'Email Logs',
    'section_notes'                 => 'Notes',

    // Fields
    'field_organization'        => 'Organization',
    'field_purpose'             => 'Purpose',
    'field_purpose_label'       => 'Purpose Label',
    'field_amount'              => 'Amount',
    'field_tax'                 => 'Tax',
    'field_total'               => 'Total Amount',
    'field_currency'            => 'Currency',
    'field_status'              => 'Status',
    'field_gateway'             => 'Gateway',
    'field_tran_ref'            => 'Transaction Ref',
    'field_tran_type'           => 'Transaction Type',
    'field_response_status'     => 'Response Status',
    'field_response_code'       => 'Response Code',
    'field_response_message'    => 'Response Message',
    'field_card_type'           => 'Card Type',
    'field_card_scheme'         => 'Card Scheme',
    'field_payment_description' => 'Payment Description',
    'field_email_sent'          => 'Email Sent',
    'field_email_sent_at'       => 'Email Sent At',
    'field_email_error'         => 'Email Error',
    'field_invoice_generated'   => 'Invoice Generated',
    'field_invoice_generated_at'=> 'Invoice Generated At',
    'field_ipn_received'        => 'IPN Received',
    'field_ipn_received_at'     => 'IPN Received At',
    'field_notes'               => 'Notes',
    'field_refund_amount'       => 'Refund Amount',
    'field_refund_reason'       => 'Refund Reason',
    'field_refund_tran_ref'     => 'Refund Transaction Ref',
    'field_refunded_at'         => 'Refunded At',

    // Table columns
    'col_cart_id'       => 'Cart ID',
    'col_organization'  => 'Organization',
    'col_purpose'       => 'Purpose',
    'col_purpose_label' => 'Purpose Label',
    'col_total'         => 'Total',
    'col_status'        => 'Status',
    'col_gateway'       => 'Gateway',
    'col_tran_ref'      => 'Txn Ref',
    'col_card'          => 'Card',
    'col_email'         => 'Email',
    'col_invoice'       => 'Invoice',
    'col_ipn'           => 'IPN',
    'col_date'          => 'Date',

    // Filters
    'filter_status'             => 'Status',
    'filter_purpose'            => 'Purpose',
    'filter_email_sent'         => 'Email Sent',
    'filter_invoice_generated'  => 'Invoice Generated',
    'filter_from'               => 'From Date',
    'filter_until'              => 'Until Date',

    // Actions
    'action_resend_email'   => 'Resend Email',
    'action_query_gateway'  => 'Query Gateway',
    'action_refund'         => 'Process Refund',

    // Notifications
    'email_resent_success'      => 'Confirmation email resent successfully.',
    'email_resent_failed'       => 'Failed to resend confirmation email.',
    'gateway_query_success'     => 'Gateway transaction data updated.',
    'gateway_query_failed'      => 'Failed to query gateway. Check logs.',
    'refund_confirm_desc'       => 'This action will process a refund through PayTabs. This cannot be undone.',
    'refund_success'            => 'Refund processed successfully.',

    // Stats
    'stat_total_revenue'        => 'Total Revenue',
    'stat_total_revenue_desc'   => 'All completed payments',
    'stat_pending'              => 'Pending',
    'stat_pending_desc'         => 'Awaiting payment',
    'stat_completed_today'      => 'Completed Today',
    'stat_completed_today_desc' => 'Payments completed today',
    'stat_failed'               => 'Failed',
    'stat_failed_desc'          => 'Failed payments',
    'stat_refunded'             => 'Refunded',
    'stat_refunded_desc'        => 'Total refunded amount',

    // Misc
    'no_error'  => 'No error',
    'no_notes'  => 'No notes.',

    // Email log table
    'email_log_type'        => 'Type',
    'email_log_recipient'   => 'Recipient',
    'email_log_subject'     => 'Subject',
    'email_log_status'      => 'Status',
    'email_log_date'        => 'Date',

    // Enums - Status
    'status_pending'    => 'Pending',
    'status_processing' => 'Processing',
    'status_completed'  => 'Completed',
    'status_failed'     => 'Failed',
    'status_refunded'   => 'Refunded',
    'status_voided'     => 'Voided',

    // Enums - Purpose
    'purpose_subscription'       => 'Subscription',
    'purpose_plan_addon'         => 'Plan Add-on',
    'purpose_ai_billing'         => 'AI Billing',
    'purpose_hardware'           => 'Hardware',
    'purpose_implementation_fee' => 'Implementation Fee',
    'purpose_marketplace_purchase' => 'Marketplace Purchase',
    'purpose_other'              => 'Other',

    // Enums - Email Type
    'email_type_confirmation' => 'Payment Confirmation',
    'email_type_invoice'      => 'Invoice',
    'email_type_failed'       => 'Payment Failed',
    'email_type_refund'       => 'Refund Confirmation',

    // API Messages
    'api_payment_initiated'     => 'Payment initiated successfully.',
    'api_payment_not_found'     => 'Payment not found.',
    'api_ipn_processed'         => 'IPN processed.',
    'api_ipn_invalid_signature' => 'Invalid IPN signature.',
    'api_return_verified'       => 'Payment verified.',
    'api_email_resent'          => 'Email resent successfully.',
    'api_email_resend_failed'   => 'Failed to resend email.',
    'api_refund_initiated'      => 'Refund initiated.',

    // Email templates
    'email_payment_confirmation_subject' => 'Payment Confirmation - :cart_id',
    'email_invoice_subject'              => 'Invoice - :invoice_number',
    'email_payment_failed_subject'       => 'Payment Failed - :cart_id',
    'email_refund_subject'               => 'Refund Confirmation - :cart_id',
];
