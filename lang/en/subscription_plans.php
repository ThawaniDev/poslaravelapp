<?php

return [

    // ── Navigation ────────────────────────────────────────────────────────────
    'nav_label'             => 'Subscription Plans',
    'nav_group'             => 'Billing & Subscriptions',

    // ── Resource Labels ───────────────────────────────────────────────────────
    'model_label'           => 'Subscription Plan',
    'plural_model_label'    => 'Subscription Plans',

    // ── Tabs ──────────────────────────────────────────────────────────────────
    'tab_general'           => 'General',
    'tab_pricing'           => 'Pricing',
    'tab_features'          => 'Features',
    'tab_limits'            => 'Limits',

    // ── General Tab ───────────────────────────────────────────────────────────
    'section_plan_details'              => 'Plan Details',
    'section_plan_details_desc'         => 'Basic plan information visible to subscribers',
    'section_display_settings'          => 'Display Settings',
    'field_name_en'                     => 'Plan Name (EN)',
    'field_name_ar'                     => 'Plan Name (AR)',
    'field_slug'                        => 'Slug',
    'field_description_en'              => 'Description (EN)',
    'field_description_ar'              => 'Description (AR)',
    'field_is_active'                   => 'Active',
    'field_is_active_helper'            => 'Only active plans are visible to subscribers',
    'field_is_highlighted'              => 'Highlighted / Recommended',
    'field_is_highlighted_helper'       => 'Highlight this plan on the pricing page',
    'field_sort_order'                  => 'Sort Order',
    'field_sort_order_helper'           => 'Lower numbers appear first',

    // ── Pricing Tab ───────────────────────────────────────────────────────────
    'section_pricing'                   => 'Subscription Pricing',
    'section_pricing_desc'              => 'Set monthly and annual pricing in SAR',
    'section_trial'                     => 'Trial & Grace Period',
    'section_trial_desc'                => 'Configure trial and grace periods for this plan',
    'field_monthly_price'               => 'Monthly Price (SAR)',
    'field_annual_price'                => 'Annual Price (SAR)',
    'field_annual_price_helper'         => 'Typically a ~17% discount over monthly',
    'field_trial_days'                  => 'Trial Period (days)',
    'field_trial_days_helper'           => '0 = no trial',
    'field_grace_period_days'           => 'Grace Period (days)',
    'field_grace_period_days_helper'    => 'Days after payment failure before suspension',

    // ── Features Tab ─────────────────────────────────────────────────────────
    'section_feature_toggles'           => 'Feature Toggles',
    'section_feature_toggles_desc'      => 'Enable or disable features for this plan. Each feature key maps to an application module.',
    'field_feature_key'                 => 'Feature',
    'field_feature_name_en'             => 'Feature Name (EN)',
    'field_feature_name_ar'             => 'Feature Name (AR)',
    'field_is_enabled'                  => 'Enabled',
    'action_add_feature'                => 'Add Feature Toggle',
    'new_feature_label'                 => 'New Feature',

    // Feature key option labels
    'feature_pos'                       => 'Point of Sale (POS)',
    'feature_zatca_phase2'              => 'ZATCA Phase 2',
    'feature_inventory'                 => 'Inventory Management',
    'feature_reports_basic'             => 'Basic Reports',
    'feature_barcode_scanning'          => 'Barcode Scanning',
    'feature_cash_drawer'               => 'Cash Drawer',
    'feature_customer_display'          => 'Customer Display',
    'feature_receipt_printing'          => 'Receipt Printing',
    'feature_offline_mode'              => 'Offline Mode',
    'feature_mada_payments'             => 'Mada & Card Payments',
    'feature_reports_advanced'          => 'Advanced Analytics',
    'feature_multi_branch'              => 'Multi-Branch',
    'feature_delivery_integration'      => 'Delivery Integration',
    'feature_customer_loyalty'          => 'Customer Loyalty',
    'feature_api_access'                => 'API Access',
    'feature_white_label'               => 'White Label',
    'feature_priority_support'          => 'Priority Support',
    'feature_dedicated_manager'         => 'Dedicated Manager',
    'feature_custom_integrations'       => 'Custom Integrations',
    'feature_sla_guarantee'             => 'SLA Guarantee',

    // AI & Tools features
    'feature_wameed_ai'                 => 'Wameed AI',
    'feature_cashier_gamification'      => 'Cashier Gamification',
    'feature_pos_customization'         => 'POS Customization',
    'feature_companion_app'             => 'Companion App',
    'feature_installments'              => 'Installment Payments',
    'feature_accounting'                => 'Accounting',

    // Catalog & product features
    'feature_customer_management'       => 'Customer Management',
    'feature_product_modifiers'         => 'Product Modifiers',
    'feature_supplier_management'       => 'Supplier Management',
    'feature_product_variants'          => 'Product Variants',
    'feature_combo_products'            => 'Combo Products',
    'feature_bulk_import'               => 'Bulk CSV Import',
    'feature_barcode_label_printing'    => 'Barcode Label Printing',

    // Promotions & marketing features
    'feature_promotions_coupons'        => 'Promotions & Coupons',
    'feature_promotions_advanced'       => 'Advanced Promotions (BOGO, Bundles, Happy Hour)',

    // Industry vertical features
    'feature_industry_restaurant'       => 'Restaurant Features',
    'feature_industry_bakery'           => 'Bakery Features',
    'feature_industry_pharmacy'         => 'Pharmacy Features',
    'feature_industry_electronics'      => 'Electronics Features',
    'feature_industry_florist'          => 'Florist Features',
    'feature_industry_jewelry'          => 'Jewelry Features',

    // ── Limits Tab ───────────────────────────────────────────────────────────
    'section_plan_limits'               => 'Plan Limits',
    'section_plan_limits_desc'          => 'Set hard limits for each resource. Stores exceeding limits will be prompted to upgrade.',
    'field_limit_key'                   => 'Resource',
    'field_limit_value'                 => 'Limit',
    'field_limit_value_helper'          => '0 = disabled, -1 = unlimited',
    'field_overage_price'               => 'Overage Price (SAR)',
    'field_overage_price_helper'        => 'Charge per extra unit above limit',
    'action_add_limit'                  => 'Add Limit',
    'new_limit_label'                   => 'New Limit',

    // Limit key option labels
    'limit_products'                    => 'Products',
    'limit_staff_members'               => 'Staff Members',
    'limit_stores'                      => 'Stores / Branches',
    'limit_transactions_month'          => 'Transactions / Month',
    'limit_customers'                   => 'Customers',
    'limit_categories'                  => 'Categories',
    'limit_promotions'                  => 'Active Promotions',
    'limit_custom_roles'                => 'Custom Roles',
    'limit_api_calls_day'               => 'API Calls / Day',
    'limit_storage_mb'                  => 'Storage (MB)',
    'limit_reports'                     => 'Saved Reports',
    'limit_cashier_terminals'           => 'Cashier Terminals',
    'limit_branches'                    => 'Branches',
    'limit_transactions_per_month'      => 'Transactions / Month',
    'limit_pdf_reports_per_month'       => 'PDF Reports / Month',

    // ── Table Columns ─────────────────────────────────────────────────────────
    'col_plan'                          => 'Plan',
    'col_monthly'                       => 'Monthly',
    'col_annual'                        => 'Annual',
    'col_trial'                         => 'Trial',
    'col_subscribers'                   => 'Subscribers',
    'col_features'                      => 'Features',
    'col_active'                        => 'Active',
    'col_order'                         => 'Order',

    // ── Table Filters ─────────────────────────────────────────────────────────
    'filter_status'                     => 'Status',
    'filter_all_plans'                  => 'All Plans',
    'filter_active_only'                => 'Active Only',
    'filter_inactive_only'              => 'Inactive Only',
    'filter_highlighted'                => 'Highlighted',
    'filter_all'                        => 'All',
    'filter_not_highlighted'            => 'Not Highlighted',

    // ── Actions ───────────────────────────────────────────────────────────────
    'action_toggle_active_deactivate'   => 'Deactivate',
    'action_toggle_active_activate'     => 'Activate',
    'action_duplicate'                  => 'Duplicate Plan',
    'action_duplicate_desc'             => 'This will create a new draft plan based on this one, including features and limits.',

    // ── Infolist ─────────────────────────────────────────────────────────────
    'info_plan_overview'                => 'Plan Overview',
    'info_name_en'                      => 'Name (EN)',
    'info_name_ar'                      => 'Name (AR)',
    'info_feature_key'                  => 'Feature',
    'info_feature_name_en'              => 'Name (EN)',
    'info_feature_name_ar'              => 'Name (AR)',
    'info_is_enabled'                   => 'Enabled',
    'info_resource'                     => 'Resource',
    'info_limit'                        => 'Limit',
    'info_overage'                      => 'Overage',

    // ── SoftPOS Free Tier ─────────────────────────────────────────────────────
    'section_softpos_free'                       => 'SoftPOS Free Tier',
    'section_softpos_free_desc'                  => 'Make this plan free for stores that reach a SoftPOS transaction threshold',
    'field_softpos_free_eligible'                => 'SoftPOS Free Eligible',
    'field_softpos_free_eligible_helper'         => 'When enabled, stores reaching the transaction threshold will get this plan for free',
    'field_softpos_free_threshold'               => 'Transaction Threshold',
    'field_softpos_free_threshold_helper'        => 'Number of SoftPOS transactions required to unlock free tier',
    'field_softpos_free_threshold_period'        => 'Threshold Period',
    'field_softpos_free_threshold_period_helper' => 'The time window in which transactions must be completed',
    'period_monthly'                             => 'Monthly',
    'period_quarterly'                           => 'Quarterly',
    'period_annually'                            => 'Annually',
];
