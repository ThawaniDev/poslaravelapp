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

    // ── Add-ons ───────────────────────────────────────────────────────────────
    'addon_not_found'                     => 'Add-on not found for this store.',
    'addon_already_active'                => 'This add-on is already active for your store.',
    'addon_activated'                     => 'Add-on activated successfully.',
    'addon_already_deactivated'           => 'Add-on is already deactivated.',
    'addon_removed'                       => 'Add-on removed successfully. It will not appear on your next invoice.',

    // ── Limit Key Labels (for user-facing messages) ──────────────────────────
    'limit_key_products'                  => 'products',
    'limit_key_staff_members'             => 'staff members',
    'limit_key_cashier_terminals'         => 'cashier terminals',
    'limit_key_branches'                  => 'branches',
    'limit_key_transactions_per_month'    => 'monthly transactions',
    'limit_key_storage_mb'                => 'storage',
    'limit_key_pdf_reports_per_month'     => 'monthly PDF reports',
];
