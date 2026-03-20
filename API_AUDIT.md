# API Audit Report

> **Generated:** 2026-03-17 05:39 | **Total Routes:** 718 | **Domains:** 40 | **Controllers:** 66

## Verification Criteria

For each API endpoint, verify:
1. **Supabase Compatible** — Works with PostgreSQL/Supabase (UUID PKs, proper types)
2. **Functional** — Works as expected (correct query, proper response)
3. **Auth & Permissions** — Correct guard (admin-api/sanctum), middleware, package checks
4. **Response Format** — Returns expected data structure
5. **Flutter Callable** — Can be called by Flutter Dio client
6. **Flutter Parseable** — Response can be parsed by Flutter models

## Notable Issues Found

| Severity | Issue | Location | Details |
|----------|-------|----------|--------|
| ⚠️ Medium | Raw SQL expressions | ReportService, OwnerDashboardService | `DB::raw()` aggregations — test on Supabase |
| ⚠️ Medium | Missing org-scope on show() | ProductController, CategoryController | `find()` without org check — cross-org data leak risk |
| ℹ️ Low | Global request() helper | ZatcaComplianceController, PromotionController | Uses `request()` instead of injected `$request` |
| ℹ️ Low | Placeholder route files | delivery.php, support.php, thawani.php | Empty/placeholder routes need `auth:sanctum` |
| ℹ️ Low | No explicit rate limiting | All controllers | Relies on framework defaults |

---

## Summary by Domain

| # | Domain | Routes | Auth | Controllers |
|---|--------|--------|------|-------------|
| 1 | **accessibility** | 5 | sanctum | AccessibilityController |
| 2 | **accounting** | 14 | sanctum | AccountingController |
| 3 | **admin** | 313 | admin | FeatureFlagController, PackageSubscriptionController, BillingFinanceController, ContentManagementController, DeploymentController, FinancialOperationsController, InfrastructureController, ProviderRolePermissionController, ProviderManagementController, PlatformRoleController, SecurityCenterController, SupportTicketController, AnalyticsReportingController, DataManagementController, LogMonitoringController, MarketplaceController, UserManagementController |
| 4 | **appointments** | 4 | sanctum | NiceToHaveController |
| 5 | **auth** | 12 | sanctum, none | ProfileController, LoginController, OtpController, RegisterController |
| 6 | **auto-update** | 5 | sanctum | AutoUpdateController |
| 7 | **backup** | 11 | sanctum | BackupController |
| 8 | **cash-events** | 1 | sanctum | PaymentController |
| 9 | **cash-sessions** | 4 | sanctum | PaymentController |
| 10 | **catalog** | 22 | sanctum | CategoryController, ProductController, SupplierController |
| 11 | **cfd** | 2 | sanctum | NiceToHaveController |
| 12 | **companion** | 10 | sanctum | CompanionController |
| 13 | **core** | 17 | sanctum | StoreController, OnboardingController |
| 14 | **coupons** | 2 | sanctum | PromotionController |
| 15 | **customers** | 15 | sanctum | CustomerController, LoyaltyController |
| 16 | **customization** | 10 | sanctum | CustomizationController |
| 17 | **expenses** | 2 | sanctum | PaymentController |
| 18 | **gamification** | 5 | sanctum | NiceToHaveController |
| 19 | **gift-cards** | 3 | sanctum | PaymentController |
| 20 | **gift-registry** | 5 | sanctum | NiceToHaveController |
| 21 | **hardware** | 7 | sanctum | HardwareController |
| 22 | **industry** | 59 | sanctum | BakeryController, FloristController, ElectronicsController, JewelryController, PharmacyController, RestaurantController |
| 23 | **inventory** | 27 | sanctum | RecipeController, GoodsReceiptController, PurchaseOrderController, StockAdjustmentController, StockController, StockTransferController |
| 24 | **labels** | 8 | sanctum | LabelController |
| 25 | **notifications** | 10 | sanctum | NotificationController |
| 26 | **orders** | 8 | sanctum | OrderController |
| 27 | **other** | 1 | none | Closure |
| 28 | **owner-dashboard** | 10 | sanctum | OwnerDashboardController |
| 29 | **payments** | 2 | sanctum | PaymentController |
| 30 | **pos** | 12 | sanctum | PosTerminalController |
| 31 | **promotions** | 8 | sanctum | PromotionController |
| 32 | **reports** | 7 | sanctum | ReportController |
| 33 | **security** | 11 | sanctum | SecurityController |
| 34 | **settings** | 11 | sanctum | LocalizationController |
| 35 | **signage** | 4 | sanctum | NiceToHaveController |
| 36 | **staff** | 34 | sanctum | StaffUserController, RoleController, PermissionController, PinOverrideController |
| 37 | **subscription** | 19 | sanctum, none | PlanController, SubscriptionController, InvoiceController |
| 38 | **sync** | 7 | sanctum | SyncController |
| 39 | **wishlist** | 3 | sanctum | NiceToHaveController |
| 40 | **zatca** | 8 | sanctum | ZatcaComplianceController |

---

## Controller → Database Tables Reference

| Controller | Tables Used |
|------------|-------------|
| **AccessibilityController** | `user_preferences` |
| **AccountingController** | `store_accounting_configs`, `account_mappings`, `accounting_exports`, `auto_export_configs` |
| **AnalyticsReportingController** | `feature_adoption_stats`, `platform_daily_stats`, `platform_plan_stats`, `store_health_snapshots`, `admin_activity_logs` |
| **AutoUpdateController** | `app_releases`, `app_update_stats` |
| **BackupController** | `backup_history`, `database_backups`, `provider_backup_status` |
| **BakeryController** | `bakery_recipes`, `custom_cake_orders`, `production_schedules` |
| **BillingFinanceController** | `hardware_sales`, `implementation_fees`, `payment_gateway_configs`, `invoices`, `store_subscriptions` |
| **CategoryController** | `categories` |
| **CompanionController** | `stores`, `pos_sessions`, `transactions` |
| **ContentManagementController** | `platform_announcements`, `cms_pages`, `knowledge_base_articles`, `notification_templates` |
| **CustomerController** | `customers`, `customer_groups` |
| **CustomizationController** | `pos_customization_settings`, `quick_access_configs`, `receipt_templates` |
| **DataManagementController** | `products`, `categories`, `customers`, `orders` |
| **DeploymentController** | `update_rollouts`, `app_releases` |
| **ElectronicsController** | `device_imei_records`, `repair_jobs`, `trade_in_records` |
| **FeatureFlagController** | `ab_tests`, `ab_test_variants`, `feature_flags` |
| **FinancialOperationsController** | `invoices`, `invoice_line_items`, `payments`, `store_subscriptions`, `payment_gateway_configs` |
| **FloristController** | `flower_arrangements`, `flower_freshness_log`, `flower_subscriptions` |
| **GoodsReceiptController** | `goods_receipts`, `goods_receipt_items`, `stock_batches`, `stock_levels`, `stock_movements` |
| **HardwareController** | `hardware_configurations`, `hardware_event_log`, `certified_hardware` |
| **InfrastructureController** | `system_settings`, `supported_locales` |
| **InvoiceController** | `invoices`, `invoice_line_items`, `store_subscriptions` |
| **JewelryController** | `buyback_transactions`, `daily_metal_rates`, `jewelry_product_details` |
| **LabelController** | `label_print_history`, `label_templates` |
| **LocalizationController** | `master_translation_strings`, `supported_locales`, `translation_overrides`, `translation_versions` |
| **LogMonitoringController** | `admin_activity_logs`, `security_audit_log` |
| **LoginController** | `users`, `personal_access_tokens` |
| **LoyaltyController** | `customers`, `loyalty_config`, `loyalty_tiers`, `loyalty_transactions`, `loyalty_badges`, `loyalty_challenges`, `customer_badges`, `customer_challenge_progress`, `store_credit_transactions` |
| **MarketplaceController** | `thawani_marketplace_config`, `thawani_store_config`, `thawani_product_mappings`, `thawani_settlements` |
| **NiceToHaveController** | `appointments`, `cfd_configurations`, `gift_registries`, `signage_playlists`, `wishlists` |
| **NotificationController** | `fcm_tokens`, `notifications`, `notification_preferences` |
| **OnboardingController** | `onboarding_steps`, `onboarding_progress` |
| **OrderController** | `orders`, `order_items`, `order_status_history`, `returns`, `return_items` |
| **OtpController** | `otp_verifications`, `users` |
| **OwnerDashboardController** | `daily_sales_summary`, `product_sales_summary`, `products`, `stock_levels`, `pos_sessions`, `orders`, `payments`, `transactions` |
| **PackageSubscriptionController** | `store_subscriptions`, `subscription_plans`, `plan_add_ons`, `subscription_discounts` |
| **PaymentController** | `payments`, `cash_sessions`, `cash_events`, `expenses`, `gift_cards` |
| **PermissionController** | `permissions` |
| **PharmacyController** | `drug_schedules`, `prescriptions` |
| **PinOverrideController** | `pin_overrides`, `permissions` |
| **PlanController** | `subscription_plans`, `plan_feature_toggles`, `plan_limits`, `plan_add_ons` |
| **PlatformRoleController** | `admin_users`, `admin_user_roles`, `admin_activity_logs` |
| **PosTerminalController** | `pos_sessions`, `transactions`, `transaction_items`, `held_carts` |
| **ProductController** | `products`, `categories`, `product_barcodes`, `internal_barcode_sequence`, `product_variants`, `product_variant_groups`, `modifier_groups`, `modifier_options` |
| **ProfileController** | `users`, `stores`, `organizations` |
| **PromotionController** | `promotions`, `coupon_codes`, `promotion_categories`, `promotion_customer_groups` |
| **ProviderManagementController** | `organizations`, `stores`, `provider_notes`, `provider_registrations`, `store_subscriptions` |
| **ProviderRolePermissionController** | `default_role_templates`, `provider_permissions` |
| **PurchaseOrderController** | `purchase_orders`, `purchase_order_items` |
| **RecipeController** | `recipes`, `recipe_ingredients` |
| **RegisterController** | `users`, `organizations`, `stores` |
| **ReportController** | `daily_sales_summary`, `product_sales_summary`, `orders`, `staff_users`, `payments`, `transactions` |
| **RestaurantController** | `restaurant_tables`, `table_reservations`, `kitchen_tickets`, `open_tabs` |
| **RoleController** | `roles`, `role_has_permissions` |
| **SecurityCenterController** | `admin_users`, `admin_ip_allowlist`, `admin_ip_blocklist`, `admin_sessions`, `device_registrations`, `login_attempts`, `security_audit_log`, `security_policies` |
| **SecurityController** | `security_policies`, `security_audit_log`, `device_registrations`, `login_attempts`, `permissions` |
| **StaffUserController** | `staff_users`, `attendance_records`, `break_records`, `shift_schedules`, `shift_templates`, `commission_rules`, `commission_earnings`, `staff_activity_log` |
| **StockAdjustmentController** | `stock_adjustments`, `stock_adjustment_items`, `stock_levels`, `stock_movements` |
| **StockController** | `stock_levels`, `stock_movements` |
| **StockTransferController** | `stock_transfers`, `stock_transfer_items`, `stock_levels`, `stock_movements` |
| **StoreController** | `stores`, `store_settings`, `store_working_hours`, `organizations`, `onboarding_progress` |
| **SubscriptionController** | `store_subscriptions`, `subscription_plans`, `invoices`, `plan_feature_toggles`, `plan_limits`, `plan_add_ons` |
| **SupportTicketController** | `support_tickets`, `support_ticket_messages`, `canned_responses` |
| **SyncController** | `sync_log`, `sync_conflicts` |
| **UserManagementController** | `admin_users`, `admin_user_roles`, `users` |
| **ZatcaComplianceController** | `zatca_certificates`, `zatca_invoices` |

---

## Accessibility

**Tables:** `user_preferences`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/accessibility/preferences` | AccessibilityController@resetPreferences | sanctum |
| `GET` | `api/v2/accessibility/preferences` | AccessibilityController@getPreferences | sanctum |
| `GET` | `api/v2/accessibility/shortcuts` | AccessibilityController@getShortcuts | sanctum |
| `PUT` | `api/v2/accessibility/preferences` | AccessibilityController@updatePreferences | sanctum |
| `PUT` | `api/v2/accessibility/shortcuts` | AccessibilityController@updateShortcuts | sanctum |

## Accounting

**Tables:** `store_accounting_configs`, `account_mappings`, `accounting_exports`, `auto_export_configs`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/accounting/mapping/{id}` | AccountingController@deleteMapping | sanctum |
| `GET` | `api/v2/accounting/auto-export` | AccountingController@getAutoExport | sanctum |
| `GET` | `api/v2/accounting/exports/{id}` | AccountingController@getExport | sanctum |
| `GET` | `api/v2/accounting/exports` | AccountingController@listExports | sanctum |
| `GET` | `api/v2/accounting/mapping` | AccountingController@getMappings | sanctum |
| `GET` | `api/v2/accounting/pos-account-keys` | AccountingController@posAccountKeys | sanctum |
| `GET` | `api/v2/accounting/status` | AccountingController@status | sanctum |
| `POST` | `api/v2/accounting/connect` | AccountingController@connect | sanctum |
| `POST` | `api/v2/accounting/disconnect` | AccountingController@disconnect | sanctum |
| `POST` | `api/v2/accounting/exports/{id}/retry` | AccountingController@retryExport | sanctum |
| `POST` | `api/v2/accounting/exports` | AccountingController@triggerExport | sanctum |
| `POST` | `api/v2/accounting/refresh-token` | AccountingController@refreshToken | sanctum |
| `PUT` | `api/v2/accounting/auto-export` | AccountingController@updateAutoExport | sanctum |
| `PUT` | `api/v2/accounting/mapping` | AccountingController@saveMappings | sanctum |

## Admin

**Tables:** `ab_tests`, `ab_test_variants`, `feature_flags`, `store_subscriptions`, `subscription_plans`, `plan_add_ons`, `subscription_discounts`, `hardware_sales`, `implementation_fees`, `payment_gateway_configs`, `invoices`, `store_subscriptions`, `platform_announcements`, `cms_pages`, `knowledge_base_articles`, `notification_templates`, `update_rollouts`, `app_releases`, `invoices`, `invoice_line_items`, `payments`, `store_subscriptions`, `payment_gateway_configs`, `system_settings`, `supported_locales`, `default_role_templates`, `provider_permissions`, `organizations`, `stores`, `provider_notes`, `provider_registrations`, `store_subscriptions`, `admin_users`, `admin_user_roles`, `admin_activity_logs`, `admin_users`, `admin_ip_allowlist`, `admin_ip_blocklist`, `admin_sessions`, `device_registrations`, `login_attempts`, `security_audit_log`, `security_policies`, `support_tickets`, `support_ticket_messages`, `canned_responses`, `feature_adoption_stats`, `platform_daily_stats`, `platform_plan_stats`, `store_health_snapshots`, `admin_activity_logs`, `products`, `categories`, `customers`, `orders`, `admin_activity_logs`, `security_audit_log`, `thawani_marketplace_config`, `thawani_store_config`, `thawani_product_mappings`, `thawani_settlements`, `admin_users`, `admin_user_roles`, `users`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/admin/ab-tests/{testId}/variants/{variantId}` | FeatureFlagController@removeVariant | admin |
| `DELETE` | `api/v2/admin/ab-tests/{testId}` | FeatureFlagController@destroyTest | admin |
| `DELETE` | `api/v2/admin/add-ons/{addOnId}` | PackageSubscriptionController@deleteAddOn | admin |
| `DELETE` | `api/v2/admin/billing/gateways/{gatewayId}` | BillingFinanceController@deleteGateway | admin |
| `DELETE` | `api/v2/admin/billing/hardware-sales/{saleId}` | BillingFinanceController@deleteHardwareSale | admin |
| `DELETE` | `api/v2/admin/billing/implementation-fees/{feeId}` | BillingFinanceController@deleteImplementationFee | admin |
| `DELETE` | `api/v2/admin/content/announcements/{announcementId}` | ContentManagementController@destroyAnnouncement | admin |
| `DELETE` | `api/v2/admin/content/articles/{articleId}` | ContentManagementController@destroyArticle | admin |
| `DELETE` | `api/v2/admin/content/pages/{pageId}` | ContentManagementController@destroyPage | admin |
| `DELETE` | `api/v2/admin/content/templates/{templateId}` | ContentManagementController@destroyTemplate | admin |
| `DELETE` | `api/v2/admin/deployment/releases/{releaseId}` | DeploymentController@deleteRelease | admin |
| `DELETE` | `api/v2/admin/discounts/{discountId}` | PackageSubscriptionController@deleteDiscount | admin |
| `DELETE` | `api/v2/admin/feature-flags/{flagId}` | FeatureFlagController@destroy | admin |
| `DELETE` | `api/v2/admin/financial-operations/account-mappings/{id}` | FinancialOperationsController@deleteAccountMapping | admin |
| `DELETE` | `api/v2/admin/financial-operations/accounting-configs/{id}` | FinancialOperationsController@deleteAccountingConfig | admin |
| `DELETE` | `api/v2/admin/financial-operations/auto-export-configs/{id}` | FinancialOperationsController@deleteAutoExportConfig | admin |
| `DELETE` | `api/v2/admin/financial-operations/expenses/{id}` | FinancialOperationsController@deleteExpense | admin |
| `DELETE` | `api/v2/admin/infrastructure/failed-jobs/{id}` | InfrastructureController@deleteFailedJob | admin |
| `DELETE` | `api/v2/admin/plans/{planId}` | PackageSubscriptionController@deletePlan | admin |
| `DELETE` | `api/v2/admin/provider-roles/templates/{id}` | ProviderRolePermissionController@deleteTemplate | admin |
| `DELETE` | `api/v2/admin/providers/stores/{storeId}/limits/{limitKey}` | ProviderManagementController@removeLimitOverride | admin |
| `DELETE` | `api/v2/admin/roles/{roleId}` | PlatformRoleController@deleteRole | admin |
| `DELETE` | `api/v2/admin/security-center/ip-allowlist/{entryId}` | SecurityCenterController@deleteAllowlistEntry | admin |
| `DELETE` | `api/v2/admin/security-center/ip-blocklist/{entryId}` | SecurityCenterController@deleteBlocklistEntry | admin |
| `DELETE` | `api/v2/admin/support/canned-responses/{responseId}` | SupportTicketController@destroyCannedResponse | admin |
| `GET` | `api/v2/admin/ab-tests/{testId}/results` | FeatureFlagController@testResults | admin |
| `GET` | `api/v2/admin/ab-tests/{testId}` | FeatureFlagController@showTest | admin |
| `GET` | `api/v2/admin/ab-tests` | FeatureFlagController@listTests | admin |
| `GET` | `api/v2/admin/activity-log` | PlatformRoleController@listActivityLog | admin |
| `GET` | `api/v2/admin/add-ons/{addOnId}` | PackageSubscriptionController@showAddOn | admin |
| `GET` | `api/v2/admin/add-ons` | PackageSubscriptionController@listAddOns | admin |
| `GET` | `api/v2/admin/analytics/daily-stats` | AnalyticsReportingController@listDailyStats | admin |
| `GET` | `api/v2/admin/analytics/dashboard` | AnalyticsReportingController@mainDashboard | admin |
| `GET` | `api/v2/admin/analytics/feature-stats` | AnalyticsReportingController@listFeatureStats | admin |
| `GET` | `api/v2/admin/analytics/features` | AnalyticsReportingController@featureAdoptionDashboard | admin |
| `GET` | `api/v2/admin/analytics/health` | AnalyticsReportingController@systemHealthDashboard | admin |
| `GET` | `api/v2/admin/analytics/notifications` | AnalyticsReportingController@notificationAnalytics | admin |
| `GET` | `api/v2/admin/analytics/plan-stats` | AnalyticsReportingController@listPlanStats | admin |
| `GET` | `api/v2/admin/analytics/revenue` | AnalyticsReportingController@revenueDashboard | admin |
| `GET` | `api/v2/admin/analytics/store-health` | AnalyticsReportingController@listStoreHealth | admin |
| `GET` | `api/v2/admin/analytics/stores` | AnalyticsReportingController@storePerformanceDashboard | admin |
| `GET` | `api/v2/admin/analytics/subscriptions` | AnalyticsReportingController@subscriptionDashboard | admin |
| `GET` | `api/v2/admin/analytics/support` | AnalyticsReportingController@supportAnalyticsDashboard | admin |
| `GET` | `api/v2/admin/billing/failed-payments` | BillingFinanceController@listFailedPayments | admin |
| `GET` | `api/v2/admin/billing/gateways/{gatewayId}` | BillingFinanceController@showGateway | admin |
| `GET` | `api/v2/admin/billing/gateways` | BillingFinanceController@listGateways | admin |
| `GET` | `api/v2/admin/billing/hardware-sales/{saleId}` | BillingFinanceController@showHardwareSale | admin |
| `GET` | `api/v2/admin/billing/hardware-sales` | BillingFinanceController@listHardwareSales | admin |
| `GET` | `api/v2/admin/billing/implementation-fees/{feeId}` | BillingFinanceController@showImplementationFee | admin |
| `GET` | `api/v2/admin/billing/implementation-fees` | BillingFinanceController@listImplementationFees | admin |
| `GET` | `api/v2/admin/billing/invoices/{invoiceId}/pdf` | BillingFinanceController@invoicePdfUrl | admin |
| `GET` | `api/v2/admin/billing/invoices/{invoiceId}` | BillingFinanceController@showInvoice | admin |
| `GET` | `api/v2/admin/billing/invoices` | BillingFinanceController@listInvoices | admin |
| `GET` | `api/v2/admin/billing/retry-rules` | BillingFinanceController@getRetryRules | admin |
| `GET` | `api/v2/admin/billing/revenue` | BillingFinanceController@revenueDashboard | admin |
| `GET` | `api/v2/admin/content/announcements/{announcementId}` | ContentManagementController@showAnnouncement | admin |
| `GET` | `api/v2/admin/content/announcements` | ContentManagementController@listAnnouncements | admin |
| `GET` | `api/v2/admin/content/articles/{articleId}` | ContentManagementController@showArticle | admin |
| `GET` | `api/v2/admin/content/articles` | ContentManagementController@listArticles | admin |
| `GET` | `api/v2/admin/content/pages/{pageId}` | ContentManagementController@showPage | admin |
| `GET` | `api/v2/admin/content/pages` | ContentManagementController@listPages | admin |
| `GET` | `api/v2/admin/content/templates/{templateId}` | ContentManagementController@showTemplate | admin |
| `GET` | `api/v2/admin/content/templates` | ContentManagementController@listTemplates | admin |
| `GET` | `api/v2/admin/data-management/backup-history/{itemId}` | DataManagementController@showBackupHistoryItem | admin |
| `GET` | `api/v2/admin/data-management/backup-history` | DataManagementController@listBackupHistory | admin |
| `GET` | `api/v2/admin/data-management/database-backups/{backupId}` | DataManagementController@showDatabaseBackup | admin |
| `GET` | `api/v2/admin/data-management/database-backups` | DataManagementController@listDatabaseBackups | admin |
| `GET` | `api/v2/admin/data-management/overview` | DataManagementController@backupOverview | admin |
| `GET` | `api/v2/admin/data-management/provider-backup-statuses/{statusId}` | DataManagementController@showProviderBackupStatus | admin |
| `GET` | `api/v2/admin/data-management/provider-backup-statuses` | DataManagementController@listProviderBackupStatuses | admin |
| `GET` | `api/v2/admin/data-management/sync-conflicts/{conflictId}` | DataManagementController@showSyncConflict | admin |
| `GET` | `api/v2/admin/data-management/sync-conflicts` | DataManagementController@listSyncConflicts | admin |
| `GET` | `api/v2/admin/data-management/sync-logs/summary` | DataManagementController@syncLogSummary | admin |
| `GET` | `api/v2/admin/data-management/sync-logs/{logId}` | DataManagementController@showSyncLog | admin |
| `GET` | `api/v2/admin/data-management/sync-logs` | DataManagementController@listSyncLogs | admin |
| `GET` | `api/v2/admin/deployment/overview` | DeploymentController@platformOverview | admin |
| `GET` | `api/v2/admin/deployment/releases/{releaseId}/stats` | DeploymentController@listStats | admin |
| `GET` | `api/v2/admin/deployment/releases/{releaseId}/summary` | DeploymentController@releaseSummary | admin |
| `GET` | `api/v2/admin/deployment/releases/{releaseId}` | DeploymentController@showRelease | admin |
| `GET` | `api/v2/admin/deployment/releases` | DeploymentController@listReleases | admin |
| `GET` | `api/v2/admin/discounts/{discountId}` | PackageSubscriptionController@showDiscount | admin |
| `GET` | `api/v2/admin/discounts` | PackageSubscriptionController@listDiscounts | admin |
| `GET` | `api/v2/admin/feature-flags/{flagId}` | FeatureFlagController@show | admin |
| `GET` | `api/v2/admin/feature-flags` | FeatureFlagController@index | admin |
| `GET` | `api/v2/admin/financial-operations/account-mappings/{id}` | FinancialOperationsController@showAccountMapping | admin |
| `GET` | `api/v2/admin/financial-operations/account-mappings` | FinancialOperationsController@accountMappings | admin |
| `GET` | `api/v2/admin/financial-operations/accounting-configs/{id}` | FinancialOperationsController@showAccountingConfig | admin |
| `GET` | `api/v2/admin/financial-operations/accounting-configs` | FinancialOperationsController@accountingConfigs | admin |
| `GET` | `api/v2/admin/financial-operations/accounting-exports/{id}` | FinancialOperationsController@showAccountingExport | admin |
| `GET` | `api/v2/admin/financial-operations/accounting-exports` | FinancialOperationsController@accountingExports | admin |
| `GET` | `api/v2/admin/financial-operations/auto-export-configs/{id}` | FinancialOperationsController@showAutoExportConfig | admin |
| `GET` | `api/v2/admin/financial-operations/auto-export-configs` | FinancialOperationsController@autoExportConfigs | admin |
| `GET` | `api/v2/admin/financial-operations/cash-events/{id}` | FinancialOperationsController@showCashEvent | admin |
| `GET` | `api/v2/admin/financial-operations/cash-events` | FinancialOperationsController@cashEvents | admin |
| `GET` | `api/v2/admin/financial-operations/cash-sessions/{id}` | FinancialOperationsController@showCashSession | admin |
| `GET` | `api/v2/admin/financial-operations/cash-sessions` | FinancialOperationsController@cashSessions | admin |
| `GET` | `api/v2/admin/financial-operations/daily-sales-summary/{id}` | FinancialOperationsController@showDailySalesSummary | admin |
| `GET` | `api/v2/admin/financial-operations/daily-sales-summary` | FinancialOperationsController@dailySalesSummary | admin |
| `GET` | `api/v2/admin/financial-operations/expenses/{id}` | FinancialOperationsController@showExpense | admin |
| `GET` | `api/v2/admin/financial-operations/expenses` | FinancialOperationsController@expenses | admin |
| `GET` | `api/v2/admin/financial-operations/gift-card-transactions/{id}` | FinancialOperationsController@showGiftCardTransaction | admin |
| `GET` | `api/v2/admin/financial-operations/gift-card-transactions` | FinancialOperationsController@giftCardTransactions | admin |
| `GET` | `api/v2/admin/financial-operations/gift-cards/{id}` | FinancialOperationsController@showGiftCard | admin |
| `GET` | `api/v2/admin/financial-operations/gift-cards` | FinancialOperationsController@giftCards | admin |
| `GET` | `api/v2/admin/financial-operations/overview` | FinancialOperationsController@overview | admin |
| `GET` | `api/v2/admin/financial-operations/payments/{id}` | FinancialOperationsController@showPayment | admin |
| `GET` | `api/v2/admin/financial-operations/payments` | FinancialOperationsController@payments | admin |
| `GET` | `api/v2/admin/financial-operations/product-sales-summary/{id}` | FinancialOperationsController@showProductSalesSummary | admin |
| `GET` | `api/v2/admin/financial-operations/product-sales-summary` | FinancialOperationsController@productSalesSummary | admin |
| `GET` | `api/v2/admin/financial-operations/refunds/{id}` | FinancialOperationsController@showRefund | admin |
| `GET` | `api/v2/admin/financial-operations/refunds` | FinancialOperationsController@refunds | admin |
| `GET` | `api/v2/admin/financial-operations/thawani-orders/{id}` | FinancialOperationsController@showThawaniOrder | admin |
| `GET` | `api/v2/admin/financial-operations/thawani-orders` | FinancialOperationsController@thawaniOrders | admin |
| `GET` | `api/v2/admin/financial-operations/thawani-settlements/{id}` | FinancialOperationsController@showThawaniSettlement | admin |
| `GET` | `api/v2/admin/financial-operations/thawani-settlements` | FinancialOperationsController@thawaniSettlements | admin |
| `GET` | `api/v2/admin/financial-operations/thawani-store-configs/{id}` | FinancialOperationsController@showThawaniStoreConfig | admin |
| `GET` | `api/v2/admin/financial-operations/thawani-store-configs` | FinancialOperationsController@thawaniStoreConfigs | admin |
| `GET` | `api/v2/admin/infrastructure/cache/stats` | InfrastructureController@cacheStats | admin |
| `GET` | `api/v2/admin/infrastructure/database-backups/{id}` | InfrastructureController@showDatabaseBackup | admin |
| `GET` | `api/v2/admin/infrastructure/database-backups` | InfrastructureController@databaseBackups | admin |
| `GET` | `api/v2/admin/infrastructure/failed-jobs/{id}` | InfrastructureController@showFailedJob | admin |
| `GET` | `api/v2/admin/infrastructure/failed-jobs` | InfrastructureController@failedJobs | admin |
| `GET` | `api/v2/admin/infrastructure/health-checks/{id}` | InfrastructureController@showHealthCheck | admin |
| `GET` | `api/v2/admin/infrastructure/health-checks` | InfrastructureController@healthChecks | admin |
| `GET` | `api/v2/admin/infrastructure/overview` | InfrastructureController@overview | admin |
| `GET` | `api/v2/admin/infrastructure/provider-backups/{id}` | InfrastructureController@showProviderBackup | admin |
| `GET` | `api/v2/admin/infrastructure/provider-backups` | InfrastructureController@providerBackups | admin |
| `GET` | `api/v2/admin/infrastructure/server-metrics` | InfrastructureController@serverMetrics | admin |
| `GET` | `api/v2/admin/infrastructure/storage-usage` | InfrastructureController@storageUsage | admin |
| `GET` | `api/v2/admin/infrastructure/system-settings/{id}` | InfrastructureController@showSystemSetting | admin |
| `GET` | `api/v2/admin/infrastructure/system-settings` | InfrastructureController@systemSettings | admin |
| `GET` | `api/v2/admin/invoices/{invoiceId}` | PackageSubscriptionController@showInvoice | admin |
| `GET` | `api/v2/admin/invoices` | PackageSubscriptionController@listInvoices | admin |
| `GET` | `api/v2/admin/logs/activity/{logId}` | LogMonitoringController@showActivityLog | admin |
| `GET` | `api/v2/admin/logs/activity` | LogMonitoringController@listActivityLogs | admin |
| `GET` | `api/v2/admin/logs/events/{eventId}` | LogMonitoringController@showPlatformEvent | admin |
| `GET` | `api/v2/admin/logs/events` | LogMonitoringController@listPlatformEvents | admin |
| `GET` | `api/v2/admin/logs/health/checks` | LogMonitoringController@listHealthChecks | admin |
| `GET` | `api/v2/admin/logs/health/dashboard` | LogMonitoringController@healthDashboard | admin |
| `GET` | `api/v2/admin/logs/notifications` | LogMonitoringController@listNotificationLogs | admin |
| `GET` | `api/v2/admin/logs/security-alerts/{alertId}` | LogMonitoringController@showSecurityAlert | admin |
| `GET` | `api/v2/admin/logs/security-alerts` | LogMonitoringController@listSecurityAlerts | admin |
| `GET` | `api/v2/admin/logs/store-health` | LogMonitoringController@listStoreHealth | admin |
| `GET` | `api/v2/admin/marketplace/orders/{orderId}` | MarketplaceController@showOrder | admin |
| `GET` | `api/v2/admin/marketplace/orders` | MarketplaceController@listOrders | admin |
| `GET` | `api/v2/admin/marketplace/products/{mappingId}` | MarketplaceController@showProduct | admin |
| `GET` | `api/v2/admin/marketplace/products` | MarketplaceController@listProducts | admin |
| `GET` | `api/v2/admin/marketplace/settlements/summary` | MarketplaceController@settlementSummary | admin |
| `GET` | `api/v2/admin/marketplace/settlements/{settlementId}` | MarketplaceController@showSettlement | admin |
| `GET` | `api/v2/admin/marketplace/settlements` | MarketplaceController@listSettlements | admin |
| `GET` | `api/v2/admin/marketplace/stores/{configId}` | MarketplaceController@showStore | admin |
| `GET` | `api/v2/admin/marketplace/stores` | MarketplaceController@listStores | admin |
| `GET` | `api/v2/admin/me` | PlatformRoleController@me | admin |
| `GET` | `api/v2/admin/permissions` | PlatformRoleController@listPermissions | admin |
| `GET` | `api/v2/admin/plans/compare` | PackageSubscriptionController@comparePlans | admin |
| `GET` | `api/v2/admin/plans/{planId}` | PackageSubscriptionController@showPlan | admin |
| `GET` | `api/v2/admin/plans` | PackageSubscriptionController@listPlans | admin |
| `GET` | `api/v2/admin/provider-roles/permissions` | ProviderRolePermissionController@permissions | admin |
| `GET` | `api/v2/admin/provider-roles/templates/{id}/permissions` | ProviderRolePermissionController@templatePermissions | admin |
| `GET` | `api/v2/admin/provider-roles/templates/{id}` | ProviderRolePermissionController@showTemplate | admin |
| `GET` | `api/v2/admin/provider-roles/templates` | ProviderRolePermissionController@templates | admin |
| `GET` | `api/v2/admin/providers/notes/{organizationId}` | ProviderManagementController@listNotes | admin |
| `GET` | `api/v2/admin/providers/registrations` | ProviderManagementController@listRegistrations | admin |
| `GET` | `api/v2/admin/providers/stores/{storeId}/limits` | ProviderManagementController@listLimitOverrides | admin |
| `GET` | `api/v2/admin/providers/stores/{storeId}/metrics` | ProviderManagementController@storeMetrics | admin |
| `GET` | `api/v2/admin/providers/stores/{storeId}` | ProviderManagementController@showStore | admin |
| `GET` | `api/v2/admin/providers/stores` | ProviderManagementController@listStores | admin |
| `GET` | `api/v2/admin/revenue-dashboard` | PackageSubscriptionController@revenueDashboard | admin |
| `GET` | `api/v2/admin/roles/{roleId}` | PlatformRoleController@showRole | admin |
| `GET` | `api/v2/admin/roles` | PlatformRoleController@listRoles | admin |
| `GET` | `api/v2/admin/security-center/alerts/{alertId}` | SecurityCenterController@showAlert | admin |
| `GET` | `api/v2/admin/security-center/alerts` | SecurityCenterController@listAlerts | admin |
| `GET` | `api/v2/admin/security-center/audit-logs/{logId}` | SecurityCenterController@showAuditLog | admin |
| `GET` | `api/v2/admin/security-center/audit-logs` | SecurityCenterController@listAuditLogs | admin |
| `GET` | `api/v2/admin/security-center/devices/{deviceId}` | SecurityCenterController@showDevice | admin |
| `GET` | `api/v2/admin/security-center/devices` | SecurityCenterController@listDevices | admin |
| `GET` | `api/v2/admin/security-center/ip-allowlist` | SecurityCenterController@listAllowlist | admin |
| `GET` | `api/v2/admin/security-center/ip-blocklist` | SecurityCenterController@listBlocklist | admin |
| `GET` | `api/v2/admin/security-center/login-attempts/{attemptId}` | SecurityCenterController@showLoginAttempt | admin |
| `GET` | `api/v2/admin/security-center/login-attempts` | SecurityCenterController@listLoginAttempts | admin |
| `GET` | `api/v2/admin/security-center/overview` | SecurityCenterController@overview | admin |
| `GET` | `api/v2/admin/security-center/policies/{policyId}` | SecurityCenterController@showPolicy | admin |
| `GET` | `api/v2/admin/security-center/policies` | SecurityCenterController@listPolicies | admin |
| `GET` | `api/v2/admin/security-center/sessions/{sessionId}` | SecurityCenterController@showSession | admin |
| `GET` | `api/v2/admin/security-center/sessions` | SecurityCenterController@listSessions | admin |
| `GET` | `api/v2/admin/subscriptions/{subscriptionId}` | PackageSubscriptionController@showSubscription | admin |
| `GET` | `api/v2/admin/subscriptions` | PackageSubscriptionController@listSubscriptions | admin |
| `GET` | `api/v2/admin/support/canned-responses/{responseId}` | SupportTicketController@showCannedResponse | admin |
| `GET` | `api/v2/admin/support/canned-responses` | SupportTicketController@listCannedResponses | admin |
| `GET` | `api/v2/admin/support/tickets/{ticketId}/messages` | SupportTicketController@listMessages | admin |
| `GET` | `api/v2/admin/support/tickets/{ticketId}` | SupportTicketController@showTicket | admin |
| `GET` | `api/v2/admin/support/tickets` | SupportTicketController@listTickets | admin |
| `GET` | `api/v2/admin/team/{userId}` | PlatformRoleController@showTeamUser | admin |
| `GET` | `api/v2/admin/team` | PlatformRoleController@listTeam | admin |
| `GET` | `api/v2/admin/users/admins/{userId}/activity` | UserManagementController@adminUserActivity | admin |
| `GET` | `api/v2/admin/users/admins/{userId}` | UserManagementController@showAdminUser | admin |
| `GET` | `api/v2/admin/users/admins` | UserManagementController@listAdminUsers | admin |
| `GET` | `api/v2/admin/users/provider/{userId}/activity` | UserManagementController@providerUserActivity | admin |
| `GET` | `api/v2/admin/users/provider/{userId}` | UserManagementController@showProviderUser | admin |
| `GET` | `api/v2/admin/users/provider` | UserManagementController@listProviderUsers | admin |
| `POST` | `api/v2/admin/ab-tests/{testId}/start` | FeatureFlagController@startTest | admin |
| `POST` | `api/v2/admin/ab-tests/{testId}/stop` | FeatureFlagController@stopTest | admin |
| `POST` | `api/v2/admin/ab-tests/{testId}/variants` | FeatureFlagController@addVariant | admin |
| `POST` | `api/v2/admin/ab-tests` | FeatureFlagController@createTest | admin |
| `POST` | `api/v2/admin/add-ons` | PackageSubscriptionController@createAddOn | admin |
| `POST` | `api/v2/admin/analytics/export/revenue` | AnalyticsReportingController@exportRevenue | admin |
| `POST` | `api/v2/admin/analytics/export/stores` | AnalyticsReportingController@exportStores | admin |
| `POST` | `api/v2/admin/analytics/export/subscriptions` | AnalyticsReportingController@exportSubscriptions | admin |
| `POST` | `api/v2/admin/billing/failed-payments/{invoiceId}/retry` | BillingFinanceController@retryPayment | admin |
| `POST` | `api/v2/admin/billing/gateways/{gatewayId}/test` | BillingFinanceController@testGatewayConnection | admin |
| `POST` | `api/v2/admin/billing/gateways` | BillingFinanceController@createGateway | admin |
| `POST` | `api/v2/admin/billing/hardware-sales` | BillingFinanceController@createHardwareSale | admin |
| `POST` | `api/v2/admin/billing/implementation-fees` | BillingFinanceController@createImplementationFee | admin |
| `POST` | `api/v2/admin/billing/invoices/{invoiceId}/mark-paid` | BillingFinanceController@markInvoicePaid | admin |
| `POST` | `api/v2/admin/billing/invoices/{invoiceId}/refund` | BillingFinanceController@processRefund | admin |
| `POST` | `api/v2/admin/billing/invoices` | BillingFinanceController@createManualInvoice | admin |
| `POST` | `api/v2/admin/content/announcements` | ContentManagementController@createAnnouncement | admin |
| `POST` | `api/v2/admin/content/articles/{articleId}/publish` | ContentManagementController@publishArticle | admin |
| `POST` | `api/v2/admin/content/articles` | ContentManagementController@createArticle | admin |
| `POST` | `api/v2/admin/content/pages/{pageId}/publish` | ContentManagementController@publishPage | admin |
| `POST` | `api/v2/admin/content/pages` | ContentManagementController@createPage | admin |
| `POST` | `api/v2/admin/content/templates/{templateId}/toggle` | ContentManagementController@toggleTemplate | admin |
| `POST` | `api/v2/admin/content/templates` | ContentManagementController@createTemplate | admin |
| `POST` | `api/v2/admin/data-management/database-backups/{backupId}/complete` | DataManagementController@completeDatabaseBackup | admin |
| `POST` | `api/v2/admin/data-management/database-backups` | DataManagementController@createDatabaseBackup | admin |
| `POST` | `api/v2/admin/data-management/sync-conflicts/{conflictId}/resolve` | DataManagementController@resolveSyncConflict | admin |
| `POST` | `api/v2/admin/deployment/releases/{releaseId}/activate` | DeploymentController@activateRelease | admin |
| `POST` | `api/v2/admin/deployment/releases/{releaseId}/deactivate` | DeploymentController@deactivateRelease | admin |
| `POST` | `api/v2/admin/deployment/releases/{releaseId}/stats` | DeploymentController@recordStat | admin |
| `POST` | `api/v2/admin/deployment/releases` | DeploymentController@createRelease | admin |
| `POST` | `api/v2/admin/discounts` | PackageSubscriptionController@createDiscount | admin |
| `POST` | `api/v2/admin/feature-flags/{flagId}/toggle` | FeatureFlagController@toggle | admin |
| `POST` | `api/v2/admin/feature-flags` | FeatureFlagController@store | admin |
| `POST` | `api/v2/admin/financial-operations/account-mappings` | FinancialOperationsController@createAccountMapping | admin |
| `POST` | `api/v2/admin/financial-operations/accounting-configs` | FinancialOperationsController@createAccountingConfig | admin |
| `POST` | `api/v2/admin/financial-operations/accounting-exports/{id}/retry` | FinancialOperationsController@retryAccountingExport | admin |
| `POST` | `api/v2/admin/financial-operations/accounting-exports` | FinancialOperationsController@triggerAccountingExport | admin |
| `POST` | `api/v2/admin/financial-operations/auto-export-configs` | FinancialOperationsController@createAutoExportConfig | admin |
| `POST` | `api/v2/admin/financial-operations/cash-sessions/{id}/force-close` | FinancialOperationsController@forceCloseCashSession | admin |
| `POST` | `api/v2/admin/financial-operations/expenses` | FinancialOperationsController@createExpense | admin |
| `POST` | `api/v2/admin/financial-operations/gift-cards/{id}/void` | FinancialOperationsController@voidGiftCard | admin |
| `POST` | `api/v2/admin/financial-operations/gift-cards` | FinancialOperationsController@issueGiftCard | admin |
| `POST` | `api/v2/admin/financial-operations/refunds/{id}/process` | FinancialOperationsController@processRefund | admin |
| `POST` | `api/v2/admin/financial-operations/thawani-settlements/{id}/reconcile` | FinancialOperationsController@reconcileThawaniSettlement | admin |
| `POST` | `api/v2/admin/infrastructure/cache/flush` | InfrastructureController@flushCache | admin |
| `POST` | `api/v2/admin/infrastructure/failed-jobs/{id}/retry` | InfrastructureController@retryFailedJob | admin |
| `POST` | `api/v2/admin/logs/events` | LogMonitoringController@createPlatformEvent | admin |
| `POST` | `api/v2/admin/logs/health/checks` | LogMonitoringController@createHealthCheck | admin |
| `POST` | `api/v2/admin/logs/security-alerts/{alertId}/resolve` | LogMonitoringController@resolveSecurityAlert | admin |
| `POST` | `api/v2/admin/marketplace/products/bulk-publish` | MarketplaceController@bulkPublish | admin |
| `POST` | `api/v2/admin/marketplace/stores/{configId}/disconnect` | MarketplaceController@disconnectStore | admin |
| `POST` | `api/v2/admin/marketplace/stores/{storeId}/connect` | MarketplaceController@connectStore | admin |
| `POST` | `api/v2/admin/plans/{planId}/toggle` | PackageSubscriptionController@togglePlan | admin |
| `POST` | `api/v2/admin/plans` | PackageSubscriptionController@createPlan | admin |
| `POST` | `api/v2/admin/provider-roles/templates` | ProviderRolePermissionController@createTemplate | admin |
| `POST` | `api/v2/admin/providers/notes` | ProviderManagementController@addNote | admin |
| `POST` | `api/v2/admin/providers/registrations/{registrationId}/approve` | ProviderManagementController@approveRegistration | admin |
| `POST` | `api/v2/admin/providers/registrations/{registrationId}/reject` | ProviderManagementController@rejectRegistration | admin |
| `POST` | `api/v2/admin/providers/stores/create` | ProviderManagementController@createStore | admin |
| `POST` | `api/v2/admin/providers/stores/export` | ProviderManagementController@exportStores | admin |
| `POST` | `api/v2/admin/providers/stores/{storeId}/activate` | ProviderManagementController@activateStore | admin |
| `POST` | `api/v2/admin/providers/stores/{storeId}/limits` | ProviderManagementController@setLimitOverride | admin |
| `POST` | `api/v2/admin/providers/stores/{storeId}/suspend` | ProviderManagementController@suspendStore | admin |
| `POST` | `api/v2/admin/roles` | PlatformRoleController@createRole | admin |
| `POST` | `api/v2/admin/security-center/alerts/{alertId}/resolve` | SecurityCenterController@resolveAlert | admin |
| `POST` | `api/v2/admin/security-center/devices/{deviceId}/wipe` | SecurityCenterController@wipeDevice | admin |
| `POST` | `api/v2/admin/security-center/ip-allowlist` | SecurityCenterController@createAllowlistEntry | admin |
| `POST` | `api/v2/admin/security-center/ip-blocklist` | SecurityCenterController@createBlocklistEntry | admin |
| `POST` | `api/v2/admin/security-center/sessions/{sessionId}/revoke` | SecurityCenterController@revokeSession | admin |
| `POST` | `api/v2/admin/support/canned-responses/{responseId}/toggle` | SupportTicketController@toggleCannedResponse | admin |
| `POST` | `api/v2/admin/support/canned-responses` | SupportTicketController@createCannedResponse | admin |
| `POST` | `api/v2/admin/support/tickets/{ticketId}/assign` | SupportTicketController@assignTicket | admin |
| `POST` | `api/v2/admin/support/tickets/{ticketId}/messages` | SupportTicketController@addMessage | admin |
| `POST` | `api/v2/admin/support/tickets/{ticketId}/status` | SupportTicketController@changeStatus | admin |
| `POST` | `api/v2/admin/support/tickets` | SupportTicketController@createTicket | admin |
| `POST` | `api/v2/admin/team/{userId}/activate` | PlatformRoleController@activateTeamUser | admin |
| `POST` | `api/v2/admin/team/{userId}/deactivate` | PlatformRoleController@deactivateTeamUser | admin |
| `POST` | `api/v2/admin/team` | PlatformRoleController@createTeamUser | admin |
| `POST` | `api/v2/admin/users/admins/{userId}/reset-2fa` | UserManagementController@resetAdmin2fa | admin |
| `POST` | `api/v2/admin/users/admins` | UserManagementController@inviteAdmin | admin |
| `POST` | `api/v2/admin/users/provider/{userId}/force-password-change` | UserManagementController@forcePasswordChange | admin |
| `POST` | `api/v2/admin/users/provider/{userId}/reset-password` | UserManagementController@resetPassword | admin |
| `POST` | `api/v2/admin/users/provider/{userId}/toggle-active` | UserManagementController@toggleProviderActive | admin |
| `PUT` | `api/v2/admin/ab-tests/{testId}` | FeatureFlagController@updateTest | admin |
| `PUT` | `api/v2/admin/add-ons/{addOnId}` | PackageSubscriptionController@updateAddOn | admin |
| `PUT` | `api/v2/admin/billing/gateways/{gatewayId}` | BillingFinanceController@updateGateway | admin |
| `PUT` | `api/v2/admin/billing/hardware-sales/{saleId}` | BillingFinanceController@updateHardwareSale | admin |
| `PUT` | `api/v2/admin/billing/implementation-fees/{feeId}` | BillingFinanceController@updateImplementationFee | admin |
| `PUT` | `api/v2/admin/billing/retry-rules` | BillingFinanceController@updateRetryRules | admin |
| `PUT` | `api/v2/admin/content/announcements/{announcementId}` | ContentManagementController@updateAnnouncement | admin |
| `PUT` | `api/v2/admin/content/articles/{articleId}` | ContentManagementController@updateArticle | admin |
| `PUT` | `api/v2/admin/content/pages/{pageId}` | ContentManagementController@updatePage | admin |
| `PUT` | `api/v2/admin/content/templates/{templateId}` | ContentManagementController@updateTemplate | admin |
| `PUT` | `api/v2/admin/deployment/releases/{releaseId}/rollout` | DeploymentController@updateRollout | admin |
| `PUT` | `api/v2/admin/deployment/releases/{releaseId}` | DeploymentController@updateRelease | admin |
| `PUT` | `api/v2/admin/discounts/{discountId}` | PackageSubscriptionController@updateDiscount | admin |
| `PUT` | `api/v2/admin/feature-flags/{flagId}` | FeatureFlagController@update | admin |
| `PUT` | `api/v2/admin/financial-operations/account-mappings/{id}` | FinancialOperationsController@updateAccountMapping | admin |
| `PUT` | `api/v2/admin/financial-operations/accounting-configs/{id}` | FinancialOperationsController@updateAccountingConfig | admin |
| `PUT` | `api/v2/admin/financial-operations/auto-export-configs/{id}` | FinancialOperationsController@updateAutoExportConfig | admin |
| `PUT` | `api/v2/admin/financial-operations/expenses/{id}` | FinancialOperationsController@updateExpense | admin |
| `PUT` | `api/v2/admin/financial-operations/gift-cards/{id}` | FinancialOperationsController@updateGiftCard | admin |
| `PUT` | `api/v2/admin/marketplace/products/{mappingId}` | MarketplaceController@updateProduct | admin |
| `PUT` | `api/v2/admin/marketplace/stores/{configId}` | MarketplaceController@updateStoreConfig | admin |
| `PUT` | `api/v2/admin/plans/{planId}` | PackageSubscriptionController@updatePlan | admin |
| `PUT` | `api/v2/admin/provider-roles/templates/{id}/permissions` | ProviderRolePermissionController@updateTemplatePermissions | admin |
| `PUT` | `api/v2/admin/provider-roles/templates/{id}` | ProviderRolePermissionController@updateTemplate | admin |
| `PUT` | `api/v2/admin/roles/{roleId}` | PlatformRoleController@updateRole | admin |
| `PUT` | `api/v2/admin/security-center/policies/{policyId}` | SecurityCenterController@updatePolicy | admin |
| `PUT` | `api/v2/admin/support/canned-responses/{responseId}` | SupportTicketController@updateCannedResponse | admin |
| `PUT` | `api/v2/admin/support/tickets/{ticketId}` | SupportTicketController@updateTicket | admin |
| `PUT` | `api/v2/admin/team/{userId}` | PlatformRoleController@updateTeamUser | admin |
| `PUT` | `api/v2/admin/users/admins/{userId}` | UserManagementController@updateAdmin | admin |

## Appointments

**Tables:** `appointments`, `cfd_configurations`, `gift_registries`, `signage_playlists`, `wishlists`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/appointments` | NiceToHaveController@appointments | sanctum |
| `POST` | `api/v2/appointments/{id}/cancel` | NiceToHaveController@cancelAppointment | sanctum |
| `POST` | `api/v2/appointments` | NiceToHaveController@createAppointment | sanctum |
| `PUT` | `api/v2/appointments/{id}` | NiceToHaveController@updateAppointment | sanctum |

## Auth

**Tables:** `users`, `stores`, `organizations`, `users`, `personal_access_tokens`, `otp_verifications`, `users`, `users`, `organizations`, `stores`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/auth/me` | ProfileController@me | sanctum |
| `POST` | `api/v2/auth/login/pin` | LoginController@loginByPin | none |
| `POST` | `api/v2/auth/login` | LoginController@login | none |
| `POST` | `api/v2/auth/logout-all` | LoginController@logoutAll | sanctum |
| `POST` | `api/v2/auth/logout` | LoginController@logout | sanctum |
| `POST` | `api/v2/auth/otp/send` | OtpController@send | none |
| `POST` | `api/v2/auth/otp/verify` | OtpController@verify | none |
| `POST` | `api/v2/auth/refresh` | ProfileController@refreshToken | sanctum |
| `POST` | `api/v2/auth/register` | RegisterController@ | none |
| `PUT` | `api/v2/auth/password` | ProfileController@changePassword | sanctum |
| `PUT` | `api/v2/auth/pin` | ProfileController@setPin | sanctum |
| `PUT` | `api/v2/auth/profile` | ProfileController@update | sanctum |

## Auto update

**Tables:** `app_releases`, `app_update_stats`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/auto-update/changelog` | AutoUpdateController@changelog | sanctum |
| `GET` | `api/v2/auto-update/current-version` | AutoUpdateController@currentVersion | sanctum |
| `GET` | `api/v2/auto-update/history` | AutoUpdateController@updateHistory | sanctum |
| `POST` | `api/v2/auto-update/check` | AutoUpdateController@checkForUpdate | sanctum |
| `POST` | `api/v2/auto-update/report-status` | AutoUpdateController@reportStatus | sanctum |

## Backup

**Tables:** `backup_history`, `database_backups`, `provider_backup_status`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/backup/{backupId}` | BackupController@destroy | sanctum |
| `GET` | `api/v2/backup/list` | BackupController@index | sanctum |
| `GET` | `api/v2/backup/provider-status` | BackupController@providerStatus | sanctum |
| `GET` | `api/v2/backup/schedule` | BackupController@schedule | sanctum |
| `GET` | `api/v2/backup/storage` | BackupController@storageUsage | sanctum |
| `GET` | `api/v2/backup/{backupId}` | BackupController@show | sanctum |
| `POST` | `api/v2/backup/create` | BackupController@create | sanctum |
| `POST` | `api/v2/backup/export` | BackupController@export | sanctum |
| `POST` | `api/v2/backup/{backupId}/restore` | BackupController@restore | sanctum |
| `POST` | `api/v2/backup/{backupId}/verify` | BackupController@verify | sanctum |
| `PUT` | `api/v2/backup/schedule` | BackupController@updateSchedule | sanctum |

## Cash events

**Tables:** `payments`, `cash_sessions`, `cash_events`, `expenses`, `gift_cards`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `POST` | `api/v2/cash-events` | PaymentController@createCashEvent | sanctum |

## Cash sessions

**Tables:** `payments`, `cash_sessions`, `cash_events`, `expenses`, `gift_cards`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/cash-sessions/{id}` | PaymentController@showCashSession | sanctum |
| `GET` | `api/v2/cash-sessions` | PaymentController@listCashSessions | sanctum |
| `POST` | `api/v2/cash-sessions` | PaymentController@openCashSession | sanctum |
| `PUT` | `api/v2/cash-sessions/{id}/close` | PaymentController@closeCashSession | sanctum |

## Catalog

**Tables:** `categories`, `products`, `categories`, `product_barcodes`, `internal_barcode_sequence`, `product_variants`, `product_variant_groups`, `modifier_groups`, `modifier_options`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/catalog/categories/{category}` | CategoryController@destroy | sanctum |
| `DELETE` | `api/v2/catalog/products/{product}` | ProductController@destroy | sanctum |
| `DELETE` | `api/v2/catalog/suppliers/{supplier}` | SupplierController@destroy | sanctum |
| `GET` | `api/v2/catalog/categories/{category}` | CategoryController@show | sanctum |
| `GET` | `api/v2/catalog/categories` | CategoryController@tree | sanctum |
| `GET` | `api/v2/catalog/products/catalog` | ProductController@catalog | sanctum |
| `GET` | `api/v2/catalog/products/changes` | ProductController@changes | sanctum |
| `GET` | `api/v2/catalog/products/{product}/modifiers` | ProductController@modifiers | sanctum |
| `GET` | `api/v2/catalog/products/{product}/variants` | ProductController@variants | sanctum |
| `GET` | `api/v2/catalog/products/{product}` | ProductController@show | sanctum |
| `GET` | `api/v2/catalog/products` | ProductController@index | sanctum |
| `GET` | `api/v2/catalog/suppliers/{supplier}` | SupplierController@show | sanctum |
| `GET` | `api/v2/catalog/suppliers` | SupplierController@index | sanctum |
| `POST` | `api/v2/catalog/categories` | CategoryController@store | sanctum |
| `POST` | `api/v2/catalog/products/{product}/barcode` | ProductController@generateBarcode | sanctum |
| `POST` | `api/v2/catalog/products` | ProductController@store | sanctum |
| `POST` | `api/v2/catalog/suppliers` | SupplierController@store | sanctum |
| `PUT` | `api/v2/catalog/categories/{category}` | CategoryController@update | sanctum |
| `PUT` | `api/v2/catalog/products/{product}/modifiers` | ProductController@syncModifiers | sanctum |
| `PUT` | `api/v2/catalog/products/{product}/variants` | ProductController@syncVariants | sanctum |
| `PUT` | `api/v2/catalog/products/{product}` | ProductController@update | sanctum |
| `PUT` | `api/v2/catalog/suppliers/{supplier}` | SupplierController@update | sanctum |

## Cfd

**Tables:** `appointments`, `cfd_configurations`, `gift_registries`, `signage_playlists`, `wishlists`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/cfd/config` | NiceToHaveController@cfdConfig | sanctum |
| `PUT` | `api/v2/cfd/config` | NiceToHaveController@updateCfdConfig | sanctum |

## Companion

**Tables:** `stores`, `pos_sessions`, `transactions`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/companion/preferences` | CompanionController@getPreferences | sanctum |
| `GET` | `api/v2/companion/quick-actions` | CompanionController@getQuickActions | sanctum |
| `GET` | `api/v2/companion/quick-stats` | CompanionController@quickStats | sanctum |
| `GET` | `api/v2/companion/sessions` | CompanionController@listSessions | sanctum |
| `GET` | `api/v2/companion/summary` | CompanionController@mobileSummary | sanctum |
| `POST` | `api/v2/companion/events` | CompanionController@logEvent | sanctum |
| `POST` | `api/v2/companion/sessions/{sessionId}/end` | CompanionController@endSession | sanctum |
| `POST` | `api/v2/companion/sessions` | CompanionController@registerSession | sanctum |
| `PUT` | `api/v2/companion/preferences` | CompanionController@updatePreferences | sanctum |
| `PUT` | `api/v2/companion/quick-actions` | CompanionController@updateQuickActions | sanctum |

## Core

**Tables:** `stores`, `store_settings`, `store_working_hours`, `organizations`, `onboarding_progress`, `onboarding_steps`, `onboarding_progress`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/core/business-types` | StoreController@businessTypes | sanctum |
| `GET` | `api/v2/core/onboarding/progress` | OnboardingController@progress | sanctum |
| `GET` | `api/v2/core/onboarding/steps` | OnboardingController@steps | sanctum |
| `GET` | `api/v2/core/stores/mine` | StoreController@mine | sanctum |
| `GET` | `api/v2/core/stores/{id}/settings` | StoreController@settings | sanctum |
| `GET` | `api/v2/core/stores/{id}/working-hours` | StoreController@workingHours | sanctum |
| `GET` | `api/v2/core/stores/{id}` | StoreController@show | sanctum |
| `GET` | `api/v2/core/stores` | StoreController@index | sanctum |
| `POST` | `api/v2/core/onboarding/checklist` | OnboardingController@updateChecklist | sanctum |
| `POST` | `api/v2/core/onboarding/complete-step` | OnboardingController@completeStep | sanctum |
| `POST` | `api/v2/core/onboarding/dismiss-checklist` | OnboardingController@dismissChecklist | sanctum |
| `POST` | `api/v2/core/onboarding/reset` | OnboardingController@reset | sanctum |
| `POST` | `api/v2/core/onboarding/skip` | OnboardingController@skip | sanctum |
| `POST` | `api/v2/core/stores/{id}/business-type` | StoreController@applyBusinessType | sanctum |
| `PUT` | `api/v2/core/stores/{id}/settings` | StoreController@updateSettings | sanctum |
| `PUT` | `api/v2/core/stores/{id}/working-hours` | StoreController@updateWorkingHours | sanctum |
| `PUT` | `api/v2/core/stores/{id}` | StoreController@update | sanctum |

## Coupons

**Tables:** `promotions`, `coupon_codes`, `promotion_categories`, `promotion_customer_groups`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `POST` | `api/v2/coupons/redeem` | PromotionController@redeemCoupon | sanctum |
| `POST` | `api/v2/coupons/validate` | PromotionController@validateCoupon | sanctum |

## Customers

**Tables:** `customers`, `customer_groups`, `customers`, `loyalty_config`, `loyalty_tiers`, `loyalty_transactions`, `loyalty_badges`, `loyalty_challenges`, `customer_badges`, `customer_challenge_progress`, `store_credit_transactions`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/customers/groups/{group}` | CustomerController@destroyGroup | sanctum |
| `DELETE` | `api/v2/customers/{customer}` | CustomerController@destroy | sanctum |
| `GET` | `api/v2/customers/groups/list` | CustomerController@groups | sanctum |
| `GET` | `api/v2/customers/loyalty/config` | LoyaltyController@config | sanctum |
| `GET` | `api/v2/customers/{customer}/loyalty` | LoyaltyController@loyaltyLog | sanctum |
| `GET` | `api/v2/customers/{customer}/store-credit` | LoyaltyController@storeCreditLog | sanctum |
| `GET` | `api/v2/customers/{customer}` | CustomerController@show | sanctum |
| `GET` | `api/v2/customers` | CustomerController@index | sanctum |
| `POST` | `api/v2/customers/groups` | CustomerController@storeGroup | sanctum |
| `POST` | `api/v2/customers/{customer}/loyalty/adjust` | LoyaltyController@adjustPoints | sanctum |
| `POST` | `api/v2/customers/{customer}/store-credit/top-up` | LoyaltyController@topUpCredit | sanctum |
| `POST` | `api/v2/customers` | CustomerController@store | sanctum |
| `PUT` | `api/v2/customers/groups/{group}` | CustomerController@updateGroup | sanctum |
| `PUT` | `api/v2/customers/loyalty/config` | LoyaltyController@saveConfig | sanctum |
| `PUT` | `api/v2/customers/{customer}` | CustomerController@update | sanctum |

## Customization

**Tables:** `pos_customization_settings`, `quick_access_configs`, `receipt_templates`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/customization/quick-access` | CustomizationController@resetQuickAccess | sanctum |
| `DELETE` | `api/v2/customization/receipt` | CustomizationController@resetReceiptTemplate | sanctum |
| `DELETE` | `api/v2/customization/settings` | CustomizationController@resetSettings | sanctum |
| `GET` | `api/v2/customization/export` | CustomizationController@exportAll | sanctum |
| `GET` | `api/v2/customization/quick-access` | CustomizationController@getQuickAccess | sanctum |
| `GET` | `api/v2/customization/receipt` | CustomizationController@getReceiptTemplate | sanctum |
| `GET` | `api/v2/customization/settings` | CustomizationController@getSettings | sanctum |
| `PUT` | `api/v2/customization/quick-access` | CustomizationController@updateQuickAccess | sanctum |
| `PUT` | `api/v2/customization/receipt` | CustomizationController@updateReceiptTemplate | sanctum |
| `PUT` | `api/v2/customization/settings` | CustomizationController@updateSettings | sanctum |

## Expenses

**Tables:** `payments`, `cash_sessions`, `cash_events`, `expenses`, `gift_cards`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/expenses` | PaymentController@listExpenses | sanctum |
| `POST` | `api/v2/expenses` | PaymentController@createExpense | sanctum |

## Gamification

**Tables:** `appointments`, `cfd_configurations`, `gift_registries`, `signage_playlists`, `wishlists`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/gamification/badges` | NiceToHaveController@badges | sanctum |
| `GET` | `api/v2/gamification/challenges` | NiceToHaveController@challenges | sanctum |
| `GET` | `api/v2/gamification/customer/{customerId}/badges` | NiceToHaveController@customerBadges | sanctum |
| `GET` | `api/v2/gamification/customer/{customerId}/progress` | NiceToHaveController@customerProgress | sanctum |
| `GET` | `api/v2/gamification/tiers` | NiceToHaveController@tiers | sanctum |

## Gift cards

**Tables:** `payments`, `cash_sessions`, `cash_events`, `expenses`, `gift_cards`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/gift-cards/{code}/balance` | PaymentController@checkGiftCardBalance | sanctum |
| `POST` | `api/v2/gift-cards/{code}/redeem` | PaymentController@redeemGiftCard | sanctum |
| `POST` | `api/v2/gift-cards` | PaymentController@issueGiftCard | sanctum |

## Gift registry

**Tables:** `appointments`, `cfd_configurations`, `gift_registries`, `signage_playlists`, `wishlists`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/gift-registry/share/{code}` | NiceToHaveController@registryByShareCode | sanctum |
| `GET` | `api/v2/gift-registry/{registryId}/items` | NiceToHaveController@registryItems | sanctum |
| `GET` | `api/v2/gift-registry` | NiceToHaveController@registries | sanctum |
| `POST` | `api/v2/gift-registry/{registryId}/items` | NiceToHaveController@addRegistryItem | sanctum |
| `POST` | `api/v2/gift-registry` | NiceToHaveController@createRegistry | sanctum |

## Hardware

**Tables:** `hardware_configurations`, `hardware_event_log`, `certified_hardware`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/hardware/config/{id}` | HardwareController@removeConfig | sanctum |
| `GET` | `api/v2/hardware/config` | HardwareController@listConfigs | sanctum |
| `GET` | `api/v2/hardware/event-logs` | HardwareController@eventLogs | sanctum |
| `GET` | `api/v2/hardware/supported-models` | HardwareController@supportedModels | sanctum |
| `POST` | `api/v2/hardware/config` | HardwareController@saveConfig | sanctum |
| `POST` | `api/v2/hardware/event-log` | HardwareController@recordEvent | sanctum |
| `POST` | `api/v2/hardware/test` | HardwareController@testDevice | sanctum |

## Industry

**Tables:** `bakery_recipes`, `custom_cake_orders`, `production_schedules`, `flower_arrangements`, `flower_freshness_log`, `flower_subscriptions`, `device_imei_records`, `repair_jobs`, `trade_in_records`, `buyback_transactions`, `daily_metal_rates`, `jewelry_product_details`, `drug_schedules`, `prescriptions`, `restaurant_tables`, `table_reservations`, `kitchen_tickets`, `open_tabs`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/industry/bakery/recipes/{id}` | BakeryController@deleteRecipe | sanctum |
| `DELETE` | `api/v2/industry/florist/arrangements/{id}` | FloristController@deleteArrangement | sanctum |
| `GET` | `api/v2/industry/bakery/cake-orders` | BakeryController@listCustomCakeOrders | sanctum |
| `GET` | `api/v2/industry/bakery/production-schedules` | BakeryController@listProductionSchedules | sanctum |
| `GET` | `api/v2/industry/bakery/recipes` | BakeryController@listRecipes | sanctum |
| `GET` | `api/v2/industry/electronics/imei-records` | ElectronicsController@listImeiRecords | sanctum |
| `GET` | `api/v2/industry/electronics/repair-jobs` | ElectronicsController@listRepairJobs | sanctum |
| `GET` | `api/v2/industry/electronics/trade-ins` | ElectronicsController@listTradeIns | sanctum |
| `GET` | `api/v2/industry/florist/arrangements` | FloristController@listArrangements | sanctum |
| `GET` | `api/v2/industry/florist/freshness-logs` | FloristController@listFreshnessLogs | sanctum |
| `GET` | `api/v2/industry/florist/subscriptions` | FloristController@listSubscriptions | sanctum |
| `GET` | `api/v2/industry/jewelry/buybacks` | JewelryController@listBuybacks | sanctum |
| `GET` | `api/v2/industry/jewelry/metal-rates` | JewelryController@listMetalRates | sanctum |
| `GET` | `api/v2/industry/jewelry/product-details` | JewelryController@listProductDetails | sanctum |
| `GET` | `api/v2/industry/pharmacy/drug-schedules` | PharmacyController@listDrugSchedules | sanctum |
| `GET` | `api/v2/industry/pharmacy/prescriptions` | PharmacyController@listPrescriptions | sanctum |
| `GET` | `api/v2/industry/restaurant/kitchen-tickets` | RestaurantController@listKitchenTickets | sanctum |
| `GET` | `api/v2/industry/restaurant/reservations` | RestaurantController@listReservations | sanctum |
| `GET` | `api/v2/industry/restaurant/tables` | RestaurantController@listTables | sanctum |
| `GET` | `api/v2/industry/restaurant/tabs` | RestaurantController@listOpenTabs | sanctum |
| `PATCH` | `api/v2/industry/bakery/cake-orders/{id}/status` | BakeryController@updateCustomCakeOrderStatus | sanctum |
| `PATCH` | `api/v2/industry/bakery/production-schedules/{id}/status` | BakeryController@updateProductionScheduleStatus | sanctum |
| `PATCH` | `api/v2/industry/electronics/repair-jobs/{id}/status` | ElectronicsController@updateRepairJobStatus | sanctum |
| `PATCH` | `api/v2/industry/florist/freshness-logs/{id}/status` | FloristController@updateFreshnessLogStatus | sanctum |
| `PATCH` | `api/v2/industry/florist/subscriptions/{id}/toggle` | FloristController@toggleSubscription | sanctum |
| `PATCH` | `api/v2/industry/restaurant/kitchen-tickets/{id}/status` | RestaurantController@updateKitchenTicketStatus | sanctum |
| `PATCH` | `api/v2/industry/restaurant/reservations/{id}/status` | RestaurantController@updateReservationStatus | sanctum |
| `PATCH` | `api/v2/industry/restaurant/tables/{id}/status` | RestaurantController@updateTableStatus | sanctum |
| `PATCH` | `api/v2/industry/restaurant/tabs/{id}/close` | RestaurantController@closeTab | sanctum |
| `POST` | `api/v2/industry/bakery/cake-orders` | BakeryController@createCustomCakeOrder | sanctum |
| `POST` | `api/v2/industry/bakery/production-schedules` | BakeryController@createProductionSchedule | sanctum |
| `POST` | `api/v2/industry/bakery/recipes` | BakeryController@createRecipe | sanctum |
| `POST` | `api/v2/industry/electronics/imei-records` | ElectronicsController@createImeiRecord | sanctum |
| `POST` | `api/v2/industry/electronics/repair-jobs` | ElectronicsController@createRepairJob | sanctum |
| `POST` | `api/v2/industry/electronics/trade-ins` | ElectronicsController@createTradeIn | sanctum |
| `POST` | `api/v2/industry/florist/arrangements` | FloristController@createArrangement | sanctum |
| `POST` | `api/v2/industry/florist/freshness-logs` | FloristController@createFreshnessLog | sanctum |
| `POST` | `api/v2/industry/florist/subscriptions` | FloristController@createSubscription | sanctum |
| `POST` | `api/v2/industry/jewelry/buybacks` | JewelryController@createBuyback | sanctum |
| `POST` | `api/v2/industry/jewelry/metal-rates` | JewelryController@upsertMetalRate | sanctum |
| `POST` | `api/v2/industry/jewelry/product-details` | JewelryController@createProductDetail | sanctum |
| `POST` | `api/v2/industry/pharmacy/drug-schedules` | PharmacyController@createDrugSchedule | sanctum |
| `POST` | `api/v2/industry/pharmacy/prescriptions` | PharmacyController@createPrescription | sanctum |
| `POST` | `api/v2/industry/restaurant/kitchen-tickets` | RestaurantController@createKitchenTicket | sanctum |
| `POST` | `api/v2/industry/restaurant/reservations` | RestaurantController@createReservation | sanctum |
| `POST` | `api/v2/industry/restaurant/tables` | RestaurantController@createTable | sanctum |
| `POST` | `api/v2/industry/restaurant/tabs` | RestaurantController@openTab | sanctum |
| `PUT` | `api/v2/industry/bakery/cake-orders/{id}` | BakeryController@updateCustomCakeOrder | sanctum |
| `PUT` | `api/v2/industry/bakery/production-schedules/{id}` | BakeryController@updateProductionSchedule | sanctum |
| `PUT` | `api/v2/industry/bakery/recipes/{id}` | BakeryController@updateRecipe | sanctum |
| `PUT` | `api/v2/industry/electronics/imei-records/{id}` | ElectronicsController@updateImeiRecord | sanctum |
| `PUT` | `api/v2/industry/electronics/repair-jobs/{id}` | ElectronicsController@updateRepairJob | sanctum |
| `PUT` | `api/v2/industry/florist/arrangements/{id}` | FloristController@updateArrangement | sanctum |
| `PUT` | `api/v2/industry/florist/subscriptions/{id}` | FloristController@updateSubscription | sanctum |
| `PUT` | `api/v2/industry/jewelry/product-details/{id}` | JewelryController@updateProductDetail | sanctum |
| `PUT` | `api/v2/industry/pharmacy/drug-schedules/{id}` | PharmacyController@updateDrugSchedule | sanctum |
| `PUT` | `api/v2/industry/pharmacy/prescriptions/{id}` | PharmacyController@updatePrescription | sanctum |
| `PUT` | `api/v2/industry/restaurant/reservations/{id}` | RestaurantController@updateReservation | sanctum |
| `PUT` | `api/v2/industry/restaurant/tables/{id}` | RestaurantController@updateTable | sanctum |

## Inventory

**Tables:** `recipes`, `recipe_ingredients`, `goods_receipts`, `goods_receipt_items`, `stock_batches`, `stock_levels`, `stock_movements`, `purchase_orders`, `purchase_order_items`, `stock_adjustments`, `stock_adjustment_items`, `stock_levels`, `stock_movements`, `stock_levels`, `stock_movements`, `stock_transfers`, `stock_transfer_items`, `stock_levels`, `stock_movements`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/inventory/recipes/{recipe}` | RecipeController@destroy | sanctum |
| `GET` | `api/v2/inventory/goods-receipts/{goodsReceipt}` | GoodsReceiptController@show | sanctum |
| `GET` | `api/v2/inventory/goods-receipts` | GoodsReceiptController@index | sanctum |
| `GET` | `api/v2/inventory/purchase-orders/{purchaseOrder}` | PurchaseOrderController@show | sanctum |
| `GET` | `api/v2/inventory/purchase-orders` | PurchaseOrderController@index | sanctum |
| `GET` | `api/v2/inventory/recipes/{recipe}` | RecipeController@show | sanctum |
| `GET` | `api/v2/inventory/recipes` | RecipeController@index | sanctum |
| `GET` | `api/v2/inventory/stock-adjustments/{stockAdjustment}` | StockAdjustmentController@show | sanctum |
| `GET` | `api/v2/inventory/stock-adjustments` | StockAdjustmentController@index | sanctum |
| `GET` | `api/v2/inventory/stock-levels` | StockController@levels | sanctum |
| `GET` | `api/v2/inventory/stock-movements` | StockController@movements | sanctum |
| `GET` | `api/v2/inventory/stock-transfers/{stockTransfer}` | StockTransferController@show | sanctum |
| `GET` | `api/v2/inventory/stock-transfers` | StockTransferController@index | sanctum |
| `POST` | `api/v2/inventory/goods-receipts/{goodsReceipt}/confirm` | GoodsReceiptController@confirm | sanctum |
| `POST` | `api/v2/inventory/goods-receipts` | GoodsReceiptController@store | sanctum |
| `POST` | `api/v2/inventory/purchase-orders/{purchaseOrder}/cancel` | PurchaseOrderController@cancel | sanctum |
| `POST` | `api/v2/inventory/purchase-orders/{purchaseOrder}/receive` | PurchaseOrderController@receive | sanctum |
| `POST` | `api/v2/inventory/purchase-orders/{purchaseOrder}/send` | PurchaseOrderController@send | sanctum |
| `POST` | `api/v2/inventory/purchase-orders` | PurchaseOrderController@store | sanctum |
| `POST` | `api/v2/inventory/recipes` | RecipeController@store | sanctum |
| `POST` | `api/v2/inventory/stock-adjustments` | StockAdjustmentController@store | sanctum |
| `POST` | `api/v2/inventory/stock-transfers/{stockTransfer}/approve` | StockTransferController@approve | sanctum |
| `POST` | `api/v2/inventory/stock-transfers/{stockTransfer}/cancel` | StockTransferController@cancel | sanctum |
| `POST` | `api/v2/inventory/stock-transfers/{stockTransfer}/receive` | StockTransferController@receive | sanctum |
| `POST` | `api/v2/inventory/stock-transfers` | StockTransferController@store | sanctum |
| `PUT` | `api/v2/inventory/recipes/{recipe}` | RecipeController@update | sanctum |
| `PUT` | `api/v2/inventory/stock-levels/{stockLevel}/reorder-point` | StockController@setReorderPoint | sanctum |

## Labels

**Tables:** `label_print_history`, `label_templates`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/labels/templates/{template}` | LabelController@destroy | sanctum |
| `GET` | `api/v2/labels/print-history` | LabelController@printHistory | sanctum |
| `GET` | `api/v2/labels/templates/presets` | LabelController@presets | sanctum |
| `GET` | `api/v2/labels/templates/{template}` | LabelController@show | sanctum |
| `GET` | `api/v2/labels/templates` | LabelController@index | sanctum |
| `POST` | `api/v2/labels/print-history` | LabelController@recordPrint | sanctum |
| `POST` | `api/v2/labels/templates` | LabelController@store | sanctum |
| `PUT` | `api/v2/labels/templates/{template}` | LabelController@update | sanctum |

## Notifications

**Tables:** `fcm_tokens`, `notifications`, `notification_preferences`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/notifications/fcm-tokens` | NotificationController@removeFcmToken | sanctum |
| `DELETE` | `api/v2/notifications/{id}` | NotificationController@destroy | sanctum |
| `GET` | `api/v2/notifications/preferences` | NotificationController@getPreferences | sanctum |
| `GET` | `api/v2/notifications/unread-count` | NotificationController@unreadCount | sanctum |
| `GET` | `api/v2/notifications` | NotificationController@index | sanctum |
| `POST` | `api/v2/notifications/fcm-tokens` | NotificationController@registerFcmToken | sanctum |
| `POST` | `api/v2/notifications` | NotificationController@store | sanctum |
| `PUT` | `api/v2/notifications/preferences` | NotificationController@updatePreferences | sanctum |
| `PUT` | `api/v2/notifications/read-all` | NotificationController@markAllAsRead | sanctum |
| `PUT` | `api/v2/notifications/{id}/read` | NotificationController@markAsRead | sanctum |

## Orders

**Tables:** `orders`, `order_items`, `order_status_history`, `returns`, `return_items`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/orders/returns/list` | OrderController@returns | sanctum |
| `GET` | `api/v2/orders/returns/{returnId}` | OrderController@showReturn | sanctum |
| `GET` | `api/v2/orders/{order}` | OrderController@show | sanctum |
| `GET` | `api/v2/orders` | OrderController@index | sanctum |
| `POST` | `api/v2/orders/{order}/return` | OrderController@createReturn | sanctum |
| `POST` | `api/v2/orders/{order}/void` | OrderController@void | sanctum |
| `POST` | `api/v2/orders` | OrderController@store | sanctum |
| `PUT` | `api/v2/orders/{order}/status` | OrderController@updateStatus | sanctum |

## Other

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/health` | Closure@ | none |

## Owner dashboard

**Tables:** `daily_sales_summary`, `product_sales_summary`, `products`, `stock_levels`, `pos_sessions`, `orders`, `payments`, `transactions`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/owner-dashboard/active-cashiers` | OwnerDashboardController@activeCashiers | sanctum |
| `GET` | `api/v2/owner-dashboard/branches` | OwnerDashboardController@branches | sanctum |
| `GET` | `api/v2/owner-dashboard/financial-summary` | OwnerDashboardController@financialSummary | sanctum |
| `GET` | `api/v2/owner-dashboard/hourly-sales` | OwnerDashboardController@hourlySales | sanctum |
| `GET` | `api/v2/owner-dashboard/low-stock` | OwnerDashboardController@lowStock | sanctum |
| `GET` | `api/v2/owner-dashboard/recent-orders` | OwnerDashboardController@recentOrders | sanctum |
| `GET` | `api/v2/owner-dashboard/sales-trend` | OwnerDashboardController@salesTrend | sanctum |
| `GET` | `api/v2/owner-dashboard/staff-performance` | OwnerDashboardController@staffPerformance | sanctum |
| `GET` | `api/v2/owner-dashboard/stats` | OwnerDashboardController@stats | sanctum |
| `GET` | `api/v2/owner-dashboard/top-products` | OwnerDashboardController@topProducts | sanctum |

## Payments

**Tables:** `payments`, `cash_sessions`, `cash_events`, `expenses`, `gift_cards`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/payments` | PaymentController@listPayments | sanctum |
| `POST` | `api/v2/payments` | PaymentController@createPayment | sanctum |

## Pos

**Tables:** `pos_sessions`, `transactions`, `transaction_items`, `held_carts`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/pos/held-carts/{cart}` | PosTerminalController@deleteCart | sanctum |
| `GET` | `api/v2/pos/held-carts` | PosTerminalController@heldCarts | sanctum |
| `GET` | `api/v2/pos/sessions/{session}` | PosTerminalController@showSession | sanctum |
| `GET` | `api/v2/pos/sessions` | PosTerminalController@sessions | sanctum |
| `GET` | `api/v2/pos/transactions/{transaction}` | PosTerminalController@showTransaction | sanctum |
| `GET` | `api/v2/pos/transactions` | PosTerminalController@transactions | sanctum |
| `POST` | `api/v2/pos/held-carts` | PosTerminalController@holdCart | sanctum |
| `POST` | `api/v2/pos/sessions` | PosTerminalController@openSession | sanctum |
| `POST` | `api/v2/pos/transactions/{transaction}/void` | PosTerminalController@voidTransaction | sanctum |
| `POST` | `api/v2/pos/transactions` | PosTerminalController@createTransaction | sanctum |
| `PUT` | `api/v2/pos/held-carts/{cart}/recall` | PosTerminalController@recallCart | sanctum |
| `PUT` | `api/v2/pos/sessions/{session}/close` | PosTerminalController@closeSession | sanctum |

## Promotions

**Tables:** `promotions`, `coupon_codes`, `promotion_categories`, `promotion_customer_groups`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/promotions/{promotion}` | PromotionController@destroy | sanctum |
| `GET` | `api/v2/promotions/{promotion}/analytics` | PromotionController@analytics | sanctum |
| `GET` | `api/v2/promotions/{promotion}` | PromotionController@show | sanctum |
| `GET` | `api/v2/promotions` | PromotionController@index | sanctum |
| `POST` | `api/v2/promotions/{promotion}/generate-coupons` | PromotionController@generateCoupons | sanctum |
| `POST` | `api/v2/promotions/{promotion}/toggle` | PromotionController@toggle | sanctum |
| `POST` | `api/v2/promotions` | PromotionController@store | sanctum |
| `PUT` | `api/v2/promotions/{promotion}` | PromotionController@update | sanctum |

## Reports

**Tables:** `daily_sales_summary`, `product_sales_summary`, `orders`, `staff_users`, `payments`, `transactions`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/reports/category-breakdown` | ReportController@categoryBreakdown | sanctum |
| `GET` | `api/v2/reports/dashboard` | ReportController@dashboard | sanctum |
| `GET` | `api/v2/reports/hourly-sales` | ReportController@hourlySales | sanctum |
| `GET` | `api/v2/reports/payment-methods` | ReportController@paymentMethods | sanctum |
| `GET` | `api/v2/reports/product-performance` | ReportController@productPerformance | sanctum |
| `GET` | `api/v2/reports/sales-summary` | ReportController@salesSummary | sanctum |
| `GET` | `api/v2/reports/staff-performance` | ReportController@staffPerformance | sanctum |

## Security

**Tables:** `security_policies`, `security_audit_log`, `device_registrations`, `login_attempts`, `permissions`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/security/audit-logs` | SecurityController@listAuditLogs | sanctum |
| `GET` | `api/v2/security/devices` | SecurityController@listDevices | sanctum |
| `GET` | `api/v2/security/login-attempts/failed-count` | SecurityController@failedAttemptCount | sanctum |
| `GET` | `api/v2/security/login-attempts` | SecurityController@listLoginAttempts | sanctum |
| `GET` | `api/v2/security/policy` | SecurityController@getPolicy | sanctum |
| `POST` | `api/v2/security/audit-logs` | SecurityController@recordAudit | sanctum |
| `POST` | `api/v2/security/devices` | SecurityController@registerDevice | sanctum |
| `POST` | `api/v2/security/login-attempts` | SecurityController@recordLoginAttempt | sanctum |
| `PUT` | `api/v2/security/devices/{id}/deactivate` | SecurityController@deactivateDevice | sanctum |
| `PUT` | `api/v2/security/devices/{id}/remote-wipe` | SecurityController@requestRemoteWipe | sanctum |
| `PUT` | `api/v2/security/policy` | SecurityController@updatePolicy | sanctum |

## Settings

**Tables:** `master_translation_strings`, `supported_locales`, `translation_overrides`, `translation_versions`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/settings/translation-overrides/{id}` | LocalizationController@removeOverride | sanctum |
| `GET` | `api/v2/settings/export-translations` | LocalizationController@exportTranslations | sanctum |
| `GET` | `api/v2/settings/locales` | LocalizationController@listLocales | sanctum |
| `GET` | `api/v2/settings/translation-overrides` | LocalizationController@getOverrides | sanctum |
| `GET` | `api/v2/settings/translation-versions` | LocalizationController@listVersions | sanctum |
| `GET` | `api/v2/settings/translations` | LocalizationController@getTranslations | sanctum |
| `POST` | `api/v2/settings/locales` | LocalizationController@saveLocale | sanctum |
| `POST` | `api/v2/settings/publish-translations` | LocalizationController@publishVersion | sanctum |
| `POST` | `api/v2/settings/translation-overrides` | LocalizationController@saveOverride | sanctum |
| `POST` | `api/v2/settings/translations/bulk-import` | LocalizationController@bulkImport | sanctum |
| `POST` | `api/v2/settings/translations` | LocalizationController@saveTranslation | sanctum |

## Signage

**Tables:** `appointments`, `cfd_configurations`, `gift_registries`, `signage_playlists`, `wishlists`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/signage/playlists/{id}` | NiceToHaveController@deletePlaylist | sanctum |
| `GET` | `api/v2/signage/playlists` | NiceToHaveController@playlists | sanctum |
| `POST` | `api/v2/signage/playlists` | NiceToHaveController@createPlaylist | sanctum |
| `PUT` | `api/v2/signage/playlists/{id}` | NiceToHaveController@updatePlaylist | sanctum |

## Staff

**Tables:** `staff_users`, `attendance_records`, `break_records`, `shift_schedules`, `shift_templates`, `commission_rules`, `commission_earnings`, `staff_activity_log`, `roles`, `role_has_permissions`, `permissions`, `pin_overrides`, `permissions`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/staff/members/{id}` | StaffUserController@destroy | sanctum |
| `DELETE` | `api/v2/staff/roles/{role}` | RoleController@destroy | sanctum |
| `DELETE` | `api/v2/staff/shifts/{id}` | StaffUserController@destroyShift | sanctum |
| `GET` | `api/v2/staff/attendance` | StaffUserController@attendance | sanctum |
| `GET` | `api/v2/staff/members/{id}/activity-log` | StaffUserController@activityLog | sanctum |
| `GET` | `api/v2/staff/members/{id}/commissions` | StaffUserController@commissions | sanctum |
| `GET` | `api/v2/staff/members/{id}` | StaffUserController@show | sanctum |
| `GET` | `api/v2/staff/members` | StaffUserController@index | sanctum |
| `GET` | `api/v2/staff/permissions/grouped` | PermissionController@grouped | sanctum |
| `GET` | `api/v2/staff/permissions/module/{module}` | PermissionController@forModule | sanctum |
| `GET` | `api/v2/staff/permissions/modules` | PermissionController@modules | sanctum |
| `GET` | `api/v2/staff/permissions/pin-protected` | PermissionController@pinProtected | sanctum |
| `GET` | `api/v2/staff/permissions` | PermissionController@index | sanctum |
| `GET` | `api/v2/staff/pin-override/check` | PinOverrideController@check | sanctum |
| `GET` | `api/v2/staff/pin-override/history` | PinOverrideController@history | sanctum |
| `GET` | `api/v2/staff/roles/user-permissions` | RoleController@userPermissions | sanctum |
| `GET` | `api/v2/staff/roles/{role}` | RoleController@show | sanctum |
| `GET` | `api/v2/staff/roles` | RoleController@index | sanctum |
| `GET` | `api/v2/staff/shift-templates` | StaffUserController@shiftTemplates | sanctum |
| `GET` | `api/v2/staff/shifts` | StaffUserController@shifts | sanctum |
| `POST` | `api/v2/staff/attendance/clock` | StaffUserController@clock | sanctum |
| `POST` | `api/v2/staff/members/{id}/nfc` | StaffUserController@registerNfc | sanctum |
| `POST` | `api/v2/staff/members/{id}/pin` | StaffUserController@setPin | sanctum |
| `POST` | `api/v2/staff/members` | StaffUserController@store | sanctum |
| `POST` | `api/v2/staff/pin-override` | PinOverrideController@authorizePin | sanctum |
| `POST` | `api/v2/staff/roles/{role}/assign` | RoleController@assign | sanctum |
| `POST` | `api/v2/staff/roles/{role}/unassign` | RoleController@unassign | sanctum |
| `POST` | `api/v2/staff/roles` | RoleController@store | sanctum |
| `POST` | `api/v2/staff/shift-templates` | StaffUserController@storeShiftTemplate | sanctum |
| `POST` | `api/v2/staff/shifts` | StaffUserController@storeShift | sanctum |
| `PUT|PATCH` | `api/v2/staff/roles/{role}` | RoleController@update | sanctum |
| `PUT` | `api/v2/staff/members/{id}/commission-config` | StaffUserController@setCommissionConfig | sanctum |
| `PUT` | `api/v2/staff/members/{id}` | StaffUserController@update | sanctum |
| `PUT` | `api/v2/staff/shifts/{id}` | StaffUserController@updateShift | sanctum |

## Subscription

**Tables:** `subscription_plans`, `plan_feature_toggles`, `plan_limits`, `plan_add_ons`, `store_subscriptions`, `subscription_plans`, `invoices`, `plan_feature_toggles`, `plan_limits`, `plan_add_ons`, `invoices`, `invoice_line_items`, `store_subscriptions`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/subscription/plans/{planId}` | PlanController@destroy | sanctum |
| `GET` | `api/v2/subscription/add-ons` | PlanController@addOns | none |
| `GET` | `api/v2/subscription/check-feature/{featureKey}` | SubscriptionController@checkFeature | sanctum |
| `GET` | `api/v2/subscription/check-limit/{limitKey}` | SubscriptionController@checkLimit | sanctum |
| `GET` | `api/v2/subscription/current` | SubscriptionController@current | sanctum |
| `GET` | `api/v2/subscription/invoices/{invoiceId}` | InvoiceController@show | sanctum |
| `GET` | `api/v2/subscription/invoices` | InvoiceController@index | sanctum |
| `GET` | `api/v2/subscription/plans/slug/{slug}` | PlanController@showBySlug | none |
| `GET` | `api/v2/subscription/plans/{planId}` | PlanController@show | none |
| `GET` | `api/v2/subscription/plans` | PlanController@index | none |
| `GET` | `api/v2/subscription/usage` | SubscriptionController@usage | sanctum |
| `PATCH` | `api/v2/subscription/plans/{planId}/toggle` | PlanController@toggle | sanctum |
| `POST` | `api/v2/subscription/cancel` | SubscriptionController@cancel | sanctum |
| `POST` | `api/v2/subscription/plans/compare` | PlanController@compare | none |
| `POST` | `api/v2/subscription/plans` | PlanController@store | sanctum |
| `POST` | `api/v2/subscription/resume` | SubscriptionController@resume | sanctum |
| `POST` | `api/v2/subscription/subscribe` | SubscriptionController@subscribe | sanctum |
| `PUT` | `api/v2/subscription/change-plan` | SubscriptionController@changePlan | sanctum |
| `PUT` | `api/v2/subscription/plans/{planId}` | PlanController@update | sanctum |

## Sync

**Tables:** `sync_log`, `sync_conflicts`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/sync/conflicts` | SyncController@conflicts | sanctum |
| `GET` | `api/v2/sync/full` | SyncController@full | sanctum |
| `GET` | `api/v2/sync/pull` | SyncController@pull | sanctum |
| `GET` | `api/v2/sync/status` | SyncController@status | sanctum |
| `POST` | `api/v2/sync/heartbeat` | SyncController@heartbeat | sanctum |
| `POST` | `api/v2/sync/push` | SyncController@push | sanctum |
| `POST` | `api/v2/sync/resolve-conflict/{conflictId}` | SyncController@resolveConflict | sanctum |

## Wishlist

**Tables:** `appointments`, `cfd_configurations`, `gift_registries`, `signage_playlists`, `wishlists`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `DELETE` | `api/v2/wishlist` | NiceToHaveController@removeFromWishlist | sanctum |
| `GET` | `api/v2/wishlist` | NiceToHaveController@wishlist | sanctum |
| `POST` | `api/v2/wishlist` | NiceToHaveController@addToWishlist | sanctum |

## Zatca

**Tables:** `zatca_certificates`, `zatca_invoices`

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| `GET` | `api/v2/zatca/compliance-summary` | ZatcaComplianceController@complianceSummary | sanctum |
| `GET` | `api/v2/zatca/invoices/{invoiceId}/xml` | ZatcaComplianceController@invoiceXml | sanctum |
| `GET` | `api/v2/zatca/invoices` | ZatcaComplianceController@invoices | sanctum |
| `GET` | `api/v2/zatca/vat-report` | ZatcaComplianceController@vatReport | sanctum |
| `POST` | `api/v2/zatca/enroll` | ZatcaComplianceController@enroll | sanctum |
| `POST` | `api/v2/zatca/renew` | ZatcaComplianceController@renew | sanctum |
| `POST` | `api/v2/zatca/submit-batch` | ZatcaComplianceController@submitBatch | sanctum |
| `POST` | `api/v2/zatca/submit-invoice` | ZatcaComplianceController@submitInvoice | sanctum |

