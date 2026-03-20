<?php
/**
 * Generate enhanced API Audit MD file with controller-to-table mappings.
 */

$lines = file('/tmp/api_routes_full.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$routes = [];

foreach ($lines as $line) {
    $parts = explode('|||', $line);
    if (count($parts) < 3) continue;
    
    $methods = $parts[0];
    $uri = $parts[1];
    $controller = $parts[2];
    $middleware = $parts[3] ?? '';
    
    $segments = explode('/', $uri);
    $domain = 'other';
    if (isset($segments[2]) && $segments[2] === 'v2' && isset($segments[3])) {
        if ($segments[3] === 'admin' && isset($segments[4])) {
            $domain = 'admin/' . $segments[4];
        } else {
            $domain = $segments[3];
        }
    } elseif (isset($segments[2]) && $segments[2] !== 'v2') {
        $domain = $segments[2];
    }
    
    $auth = 'none';
    if (str_contains($middleware, 'auth:admin-api')) $auth = 'admin';
    elseif (str_contains($middleware, 'auth:sanctum')) $auth = 'sanctum';
    
    $controllerShort = $controller;
    $method = '';
    if (str_contains($controller, '@')) {
        [$controllerShort, $method] = explode('@', $controller);
    }
    $controllerShort = basename(str_replace('\\', '/', $controllerShort));
    
    $cleanMethods = str_replace('GET|HEAD', 'GET', $methods);
    $cleanMethods = str_replace('|HEAD', '', $cleanMethods);
    
    $routes[$domain][] = [
        'methods' => $cleanMethods,
        'uri' => $uri,
        'controller' => $controllerShort,
        'method' => $method,
        'auth' => $auth,
        'middleware' => $middleware,
    ];
}

ksort($routes);

// Controller -> Tables mapping
$controllerTables = [
    'AccessibilityController' => 'user_preferences',
    'AccountingController' => 'store_accounting_configs, account_mappings, accounting_exports, auto_export_configs',
    'AnalyticsReportingController' => 'feature_adoption_stats, platform_daily_stats, platform_plan_stats, store_health_snapshots, admin_activity_logs',
    'AutoUpdateController' => 'app_releases, app_update_stats',
    'BackupController' => 'backup_history, database_backups, provider_backup_status',
    'BakeryController' => 'bakery_recipes, custom_cake_orders, production_schedules',
    'BillingFinanceController' => 'hardware_sales, implementation_fees, payment_gateway_configs, invoices, store_subscriptions',
    'CategoryController' => 'categories',
    'CompanionController' => 'stores, pos_sessions, transactions',
    'ContentManagementController' => 'platform_announcements, cms_pages, knowledge_base_articles, notification_templates',
    'CustomerController' => 'customers, customer_groups',
    'CustomizationController' => 'pos_customization_settings, quick_access_configs, receipt_templates',
    'DataManagementController' => 'products, categories, customers, orders',
    'DeploymentController' => 'update_rollouts, app_releases',
    'ElectronicsController' => 'device_imei_records, repair_jobs, trade_in_records',
    'FeatureFlagController' => 'ab_tests, ab_test_variants, feature_flags',
    'FinancialOperationsController' => 'invoices, invoice_line_items, payments, store_subscriptions, payment_gateway_configs',
    'FloristController' => 'flower_arrangements, flower_freshness_log, flower_subscriptions',
    'GoodsReceiptController' => 'goods_receipts, goods_receipt_items, stock_batches, stock_levels, stock_movements',
    'HardwareController' => 'hardware_configurations, hardware_event_log, certified_hardware',
    'InfrastructureController' => 'system_settings, supported_locales',
    'InvoiceController' => 'invoices, invoice_line_items, store_subscriptions',
    'JewelryController' => 'buyback_transactions, daily_metal_rates, jewelry_product_details',
    'LabelController' => 'label_print_history, label_templates',
    'LocalizationController' => 'master_translation_strings, supported_locales, translation_overrides, translation_versions',
    'LogMonitoringController' => 'admin_activity_logs, security_audit_log',
    'LoginController' => 'users, personal_access_tokens',
    'LoyaltyController' => 'customers, loyalty_config, loyalty_tiers, loyalty_transactions, loyalty_badges, loyalty_challenges, customer_badges, customer_challenge_progress, store_credit_transactions',
    'MarketplaceController' => 'thawani_marketplace_config, thawani_store_config, thawani_product_mappings, thawani_settlements',
    'NiceToHaveController' => 'appointments, cfd_configurations, gift_registries, signage_playlists, wishlists',
    'NotificationController' => 'fcm_tokens, notifications, notification_preferences',
    'OnboardingController' => 'onboarding_steps, onboarding_progress',
    'OrderController' => 'orders, order_items, order_status_history, returns, return_items',
    'OtpController' => 'otp_verifications, users',
    'OwnerDashboardController' => 'daily_sales_summary, product_sales_summary, products, stock_levels, pos_sessions, orders, payments, transactions',
    'PackageSubscriptionController' => 'store_subscriptions, subscription_plans, plan_add_ons, subscription_discounts',
    'PaymentController' => 'payments, cash_sessions, cash_events, expenses, gift_cards',
    'PermissionController' => 'permissions',
    'PharmacyController' => 'drug_schedules, prescriptions',
    'PinOverrideController' => 'pin_overrides, permissions',
    'PlanController' => 'subscription_plans, plan_feature_toggles, plan_limits, plan_add_ons',
    'PlatformRoleController' => 'admin_users, admin_user_roles, admin_activity_logs',
    'PosTerminalController' => 'pos_sessions, transactions, transaction_items, held_carts',
    'ProductController' => 'products, categories, product_barcodes, internal_barcode_sequence, product_variants, product_variant_groups, modifier_groups, modifier_options',
    'ProfileController' => 'users, stores, organizations',
    'PromotionController' => 'promotions, coupon_codes, promotion_categories, promotion_customer_groups',
    'ProviderManagementController' => 'organizations, stores, provider_notes, provider_registrations, store_subscriptions',
    'ProviderRolePermissionController' => 'default_role_templates, provider_permissions',
    'PurchaseOrderController' => 'purchase_orders, purchase_order_items',
    'RecipeController' => 'recipes, recipe_ingredients',
    'RegisterController' => 'users, organizations, stores',
    'ReportController' => 'daily_sales_summary, product_sales_summary, orders, staff_users, payments, transactions',
    'RestaurantController' => 'restaurant_tables, table_reservations, kitchen_tickets, open_tabs',
    'RoleController' => 'roles, role_has_permissions',
    'SecurityCenterController' => 'admin_users, admin_ip_allowlist, admin_ip_blocklist, admin_sessions, device_registrations, login_attempts, security_audit_log, security_policies',
    'SecurityController' => 'security_policies, security_audit_log, device_registrations, login_attempts, permissions',
    'StaffUserController' => 'staff_users, attendance_records, break_records, shift_schedules, shift_templates, commission_rules, commission_earnings, staff_activity_log',
    'StockAdjustmentController' => 'stock_adjustments, stock_adjustment_items, stock_levels, stock_movements',
    'StockController' => 'stock_levels, stock_movements',
    'StockTransferController' => 'stock_transfers, stock_transfer_items, stock_levels, stock_movements',
    'StoreController' => 'stores, store_settings, store_working_hours, organizations, onboarding_progress',
    'SubscriptionController' => 'store_subscriptions, subscription_plans, invoices, plan_feature_toggles, plan_limits, plan_add_ons',
    'SupportTicketController' => 'support_tickets, support_ticket_messages, canned_responses',
    'SyncController' => 'sync_log, sync_conflicts',
    'UserManagementController' => 'admin_users, admin_user_roles, users',
    'ZatcaComplianceController' => 'zatca_certificates, zatca_invoices',
];

// Generate MD
$md = "# API Audit Report\n\n";
$md .= "> **Generated:** " . date('Y-m-d H:i') . " | **Total Routes:** " . count($lines) . " | **Domains:** " . count($routes) . " | **Controllers:** " . count($controllerTables) . "\n\n";

$md .= "## Verification Criteria\n\n";
$md .= "For each API endpoint, verify:\n";
$md .= "1. **Supabase Compatible** — Works with PostgreSQL/Supabase (UUID PKs, proper types)\n";
$md .= "2. **Functional** — Works as expected (correct query, proper response)\n";
$md .= "3. **Auth & Permissions** — Correct guard (admin-api/sanctum), middleware, package checks\n";
$md .= "4. **Response Format** — Returns expected data structure\n";
$md .= "5. **Flutter Callable** — Can be called by Flutter Dio client\n";
$md .= "6. **Flutter Parseable** — Response can be parsed by Flutter models\n\n";

$md .= "## Notable Issues Found\n\n";
$md .= "| Severity | Issue | Location | Details |\n";
$md .= "|----------|-------|----------|--------|\n";
$md .= "| ⚠️ Medium | Raw SQL expressions | ReportService, OwnerDashboardService | `DB::raw()` aggregations — test on Supabase |\n";
$md .= "| ⚠️ Medium | Missing org-scope on show() | ProductController, CategoryController | `find()` without org check — cross-org data leak risk |\n";
$md .= "| ℹ️ Low | Global request() helper | ZatcaComplianceController, PromotionController | Uses `request()` instead of injected `\$request` |\n";
$md .= "| ℹ️ Low | Placeholder route files | delivery.php, support.php, thawani.php | Empty/placeholder routes need `auth:sanctum` |\n";
$md .= "| ℹ️ Low | No explicit rate limiting | All controllers | Relies on framework defaults |\n\n";

$md .= "---\n\n";
$md .= "## Summary by Domain\n\n";
$md .= "| # | Domain | Routes | Auth | Controllers |\n";
$md .= "|---|--------|--------|------|-------------|\n";

$i = 0;
foreach ($routes as $domain => $domainRoutes) {
    $i++;
    $authTypes = array_unique(array_column($domainRoutes, 'auth'));
    $controllers = array_unique(array_column($domainRoutes, 'controller'));
    $md .= "| $i | **" . $domain . "** | " . count($domainRoutes) . " | " . implode(', ', $authTypes) . " | " . implode(', ', $controllers) . " |\n";
}

$md .= "\n---\n\n";
$md .= "## Controller → Database Tables Reference\n\n";
$md .= "| Controller | Tables Used |\n";
$md .= "|------------|-------------|\n";
foreach ($controllerTables as $ctrl => $tables) {
    $md .= "| **$ctrl** | `" . str_replace(', ', '`, `', $tables) . "` |\n";
}

$md .= "\n---\n\n";

// Detailed sections
foreach ($routes as $domain => $domainRoutes) {
    $md .= "## " . ucfirst(str_replace(['/', '-'], [' → ', ' '], $domain)) . "\n\n";
    
    $domainControllers = array_unique(array_column($domainRoutes, 'controller'));
    $tables = [];
    foreach ($domainControllers as $ctrl) {
        if (isset($controllerTables[$ctrl])) {
            $tables[] = $controllerTables[$ctrl];
        }
    }
    if ($tables) {
        $md .= "**Tables:** `" . str_replace(', ', '`, `', implode(', ', $tables)) . "`\n\n";
    }
    
    $md .= "| Method | URI | Action | Auth |\n";
    $md .= "|--------|-----|--------|------|\n";
    
    foreach ($domainRoutes as $r) {
        $md .= "| `" . $r['methods'] . "` | `" . $r['uri'] . "` | " . $r['controller'] . "@" . $r['method'] . " | " . $r['auth'] . " |\n";
    }
    $md .= "\n";
}

file_put_contents(__DIR__ . '/API_AUDIT.md', $md);
echo "Generated API_AUDIT.md (" . number_format(strlen($md)) . " bytes, " . count($lines) . " routes in " . count($routes) . " domains)\n";
