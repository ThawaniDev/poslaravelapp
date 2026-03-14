<?php

use App\Http\Controllers\Api\Admin\AnalyticsReportingController;
use App\Http\Controllers\Api\Admin\BillingFinanceController;
use App\Http\Controllers\Api\Admin\ContentManagementController;
use App\Http\Controllers\Api\Admin\DataManagementController;
use App\Http\Controllers\Api\Admin\DeploymentController;
use App\Http\Controllers\Api\Admin\FeatureFlagController;
use App\Http\Controllers\Api\Admin\FinancialOperationsController;
use App\Http\Controllers\Api\Admin\LogMonitoringController;
use App\Http\Controllers\Api\Admin\MarketplaceController;
use App\Http\Controllers\Api\Admin\PackageSubscriptionController;
use App\Http\Controllers\Api\Admin\PlatformRoleController;
use App\Http\Controllers\Api\Admin\ProviderManagementController;
use App\Http\Controllers\Api\Admin\SecurityCenterController;
use App\Http\Controllers\Api\Admin\SupportTicketController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Platform admin API endpoints. All routes require admin authentication
| via auth:sanctum middleware.
|
*/

Route::prefix('admin')->middleware('auth:admin-api')->group(function () {

    // ─── Profile (P2) ────────────────────────────────────────────
    Route::get('me', [PlatformRoleController::class, 'me']);

    // ─── Platform Roles & Permissions (P2) ───────────────────────
    Route::get('permissions', [PlatformRoleController::class, 'listPermissions']);

    Route::prefix('roles')->group(function () {
        Route::get('/', [PlatformRoleController::class, 'listRoles']);
        Route::post('/', [PlatformRoleController::class, 'createRole']);
        Route::get('{roleId}', [PlatformRoleController::class, 'showRole']);
        Route::put('{roleId}', [PlatformRoleController::class, 'updateRole']);
        Route::delete('{roleId}', [PlatformRoleController::class, 'deleteRole']);
    });

    // ─── Admin Team Management (P2) ──────────────────────────────
    Route::prefix('team')->group(function () {
        Route::get('/', [PlatformRoleController::class, 'listTeam']);
        Route::post('/', [PlatformRoleController::class, 'createTeamUser']);
        Route::get('{userId}', [PlatformRoleController::class, 'showTeamUser']);
        Route::put('{userId}', [PlatformRoleController::class, 'updateTeamUser']);
        Route::post('{userId}/deactivate', [PlatformRoleController::class, 'deactivateTeamUser']);
        Route::post('{userId}/activate', [PlatformRoleController::class, 'activateTeamUser']);
    });

    // ─── Activity Log (P2) ───────────────────────────────────────
    Route::get('activity-log', [PlatformRoleController::class, 'listActivityLog']);

    // ─── Package & Subscription Management (P3) ─────────────────
    Route::prefix('plans')->group(function () {
        Route::get('/', [PackageSubscriptionController::class, 'listPlans']);
        Route::post('/', [PackageSubscriptionController::class, 'createPlan']);
        Route::get('compare', [PackageSubscriptionController::class, 'comparePlans']);
        Route::get('{planId}', [PackageSubscriptionController::class, 'showPlan']);
        Route::put('{planId}', [PackageSubscriptionController::class, 'updatePlan']);
        Route::post('{planId}/toggle', [PackageSubscriptionController::class, 'togglePlan']);
        Route::delete('{planId}', [PackageSubscriptionController::class, 'deletePlan']);
    });

    Route::prefix('add-ons')->group(function () {
        Route::get('/', [PackageSubscriptionController::class, 'listAddOns']);
        Route::post('/', [PackageSubscriptionController::class, 'createAddOn']);
        Route::get('{addOnId}', [PackageSubscriptionController::class, 'showAddOn']);
        Route::put('{addOnId}', [PackageSubscriptionController::class, 'updateAddOn']);
        Route::delete('{addOnId}', [PackageSubscriptionController::class, 'deleteAddOn']);
    });

    Route::prefix('discounts')->group(function () {
        Route::get('/', [PackageSubscriptionController::class, 'listDiscounts']);
        Route::post('/', [PackageSubscriptionController::class, 'createDiscount']);
        Route::get('{discountId}', [PackageSubscriptionController::class, 'showDiscount']);
        Route::put('{discountId}', [PackageSubscriptionController::class, 'updateDiscount']);
        Route::delete('{discountId}', [PackageSubscriptionController::class, 'deleteDiscount']);
    });

    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [PackageSubscriptionController::class, 'listSubscriptions']);
        Route::get('{subscriptionId}', [PackageSubscriptionController::class, 'showSubscription']);
    });

    Route::prefix('invoices')->group(function () {
        Route::get('/', [PackageSubscriptionController::class, 'listInvoices']);
        Route::get('{invoiceId}', [PackageSubscriptionController::class, 'showInvoice']);
    });

    Route::get('revenue-dashboard', [PackageSubscriptionController::class, 'revenueDashboard']);

    // ─── User Management (P4) ───────────────────────────────────
    Route::prefix('users')->group(function () {

        // Provider users (cross-store)
        Route::prefix('provider')->group(function () {
            Route::get('/', [UserManagementController::class, 'listProviderUsers']);
            Route::get('{userId}', [UserManagementController::class, 'showProviderUser']);
            Route::post('{userId}/reset-password', [UserManagementController::class, 'resetPassword']);
            Route::post('{userId}/force-password-change', [UserManagementController::class, 'forcePasswordChange']);
            Route::post('{userId}/toggle-active', [UserManagementController::class, 'toggleProviderActive']);
            Route::get('{userId}/activity', [UserManagementController::class, 'providerUserActivity']);
        });

        // Admin users (platform team)
        Route::prefix('admins')->group(function () {
            Route::get('/', [UserManagementController::class, 'listAdminUsers']);
            Route::post('/', [UserManagementController::class, 'inviteAdmin']);
            Route::get('{userId}', [UserManagementController::class, 'showAdminUser']);
            Route::put('{userId}', [UserManagementController::class, 'updateAdmin']);
            Route::post('{userId}/reset-2fa', [UserManagementController::class, 'resetAdmin2fa']);
            Route::get('{userId}/activity', [UserManagementController::class, 'adminUserActivity']);
        });
    });

    // ─── Billing & Finance (P5) ────────────────────────────────
    Route::prefix('billing')->group(function () {

        // Invoices
        Route::get('invoices', [BillingFinanceController::class, 'listInvoices']);
        Route::post('invoices', [BillingFinanceController::class, 'createManualInvoice']);
        Route::get('invoices/{invoiceId}', [BillingFinanceController::class, 'showInvoice']);
        Route::post('invoices/{invoiceId}/mark-paid', [BillingFinanceController::class, 'markInvoicePaid']);
        Route::post('invoices/{invoiceId}/refund', [BillingFinanceController::class, 'processRefund']);
        Route::get('invoices/{invoiceId}/pdf', [BillingFinanceController::class, 'invoicePdfUrl']);

        // Failed payments
        Route::get('failed-payments', [BillingFinanceController::class, 'listFailedPayments']);
        Route::post('failed-payments/{invoiceId}/retry', [BillingFinanceController::class, 'retryPayment']);

        // Retry rules
        Route::get('retry-rules', [BillingFinanceController::class, 'getRetryRules']);
        Route::put('retry-rules', [BillingFinanceController::class, 'updateRetryRules']);

        // Revenue dashboard
        Route::get('revenue', [BillingFinanceController::class, 'revenueDashboard']);

        // Payment gateways
        Route::get('gateways', [BillingFinanceController::class, 'listGateways']);
        Route::post('gateways', [BillingFinanceController::class, 'createGateway']);
        Route::get('gateways/{gatewayId}', [BillingFinanceController::class, 'showGateway']);
        Route::put('gateways/{gatewayId}', [BillingFinanceController::class, 'updateGateway']);
        Route::delete('gateways/{gatewayId}', [BillingFinanceController::class, 'deleteGateway']);
        Route::post('gateways/{gatewayId}/test', [BillingFinanceController::class, 'testGatewayConnection']);

        // Hardware sales
        Route::get('hardware-sales', [BillingFinanceController::class, 'listHardwareSales']);
        Route::post('hardware-sales', [BillingFinanceController::class, 'createHardwareSale']);
        Route::get('hardware-sales/{saleId}', [BillingFinanceController::class, 'showHardwareSale']);
        Route::put('hardware-sales/{saleId}', [BillingFinanceController::class, 'updateHardwareSale']);
        Route::delete('hardware-sales/{saleId}', [BillingFinanceController::class, 'deleteHardwareSale']);

        // Implementation / training fees
        Route::get('implementation-fees', [BillingFinanceController::class, 'listImplementationFees']);
        Route::post('implementation-fees', [BillingFinanceController::class, 'createImplementationFee']);
        Route::get('implementation-fees/{feeId}', [BillingFinanceController::class, 'showImplementationFee']);
        Route::put('implementation-fees/{feeId}', [BillingFinanceController::class, 'updateImplementationFee']);
        Route::delete('implementation-fees/{feeId}', [BillingFinanceController::class, 'deleteImplementationFee']);
    });

    // ─── Provider Management (P1) ────────────────────────────────
    Route::prefix('providers')->group(function () {

        // Store management
        Route::get('stores', [ProviderManagementController::class, 'listStores']);
        Route::post('stores/create', [ProviderManagementController::class, 'createStore']);
        Route::post('stores/export', [ProviderManagementController::class, 'exportStores']);
        Route::get('stores/{storeId}', [ProviderManagementController::class, 'showStore']);
        Route::get('stores/{storeId}/metrics', [ProviderManagementController::class, 'storeMetrics']);
        Route::post('stores/{storeId}/suspend', [ProviderManagementController::class, 'suspendStore']);
        Route::post('stores/{storeId}/activate', [ProviderManagementController::class, 'activateStore']);

        // Limit overrides
        Route::get('stores/{storeId}/limits', [ProviderManagementController::class, 'listLimitOverrides']);
        Route::post('stores/{storeId}/limits', [ProviderManagementController::class, 'setLimitOverride']);
        Route::delete('stores/{storeId}/limits/{limitKey}', [ProviderManagementController::class, 'removeLimitOverride']);

        // Registration queue
        Route::get('registrations', [ProviderManagementController::class, 'listRegistrations']);
        Route::post('registrations/{registrationId}/approve', [ProviderManagementController::class, 'approveRegistration']);
        Route::post('registrations/{registrationId}/reject', [ProviderManagementController::class, 'rejectRegistration']);

        // Internal notes
        Route::post('notes', [ProviderManagementController::class, 'addNote']);
        Route::get('notes/{organizationId}', [ProviderManagementController::class, 'listNotes']);
    });

    // ─── Analytics & Reporting (P6) ─────────────────────────────
    Route::prefix('analytics')->group(function () {

        // Dashboards
        Route::get('dashboard', [AnalyticsReportingController::class, 'mainDashboard']);
        Route::get('revenue', [AnalyticsReportingController::class, 'revenueDashboard']);
        Route::get('subscriptions', [AnalyticsReportingController::class, 'subscriptionDashboard']);
        Route::get('stores', [AnalyticsReportingController::class, 'storePerformanceDashboard']);
        Route::get('features', [AnalyticsReportingController::class, 'featureAdoptionDashboard']);
        Route::get('support', [AnalyticsReportingController::class, 'supportAnalyticsDashboard']);
        Route::get('health', [AnalyticsReportingController::class, 'systemHealthDashboard']);
        Route::get('notifications', [AnalyticsReportingController::class, 'notificationAnalytics']);

        // Raw data access
        Route::get('daily-stats', [AnalyticsReportingController::class, 'listDailyStats']);
        Route::get('plan-stats', [AnalyticsReportingController::class, 'listPlanStats']);
        Route::get('feature-stats', [AnalyticsReportingController::class, 'listFeatureStats']);
        Route::get('store-health', [AnalyticsReportingController::class, 'listStoreHealth']);

        // Export
        Route::post('export/revenue', [AnalyticsReportingController::class, 'exportRevenue']);
        Route::post('export/subscriptions', [AnalyticsReportingController::class, 'exportSubscriptions']);
        Route::post('export/stores', [AnalyticsReportingController::class, 'exportStores']);
    });

    // ─── Feature Flags & A/B Testing (P7) ───────────────────
    Route::prefix('feature-flags')->group(function () {
        Route::get('/', [FeatureFlagController::class, 'index']);
        Route::post('/', [FeatureFlagController::class, 'store']);
        Route::get('{flagId}', [FeatureFlagController::class, 'show']);
        Route::put('{flagId}', [FeatureFlagController::class, 'update']);
        Route::delete('{flagId}', [FeatureFlagController::class, 'destroy']);
        Route::post('{flagId}/toggle', [FeatureFlagController::class, 'toggle']);
    });

    Route::prefix('ab-tests')->group(function () {
        Route::get('/', [FeatureFlagController::class, 'listTests']);
        Route::post('/', [FeatureFlagController::class, 'createTest']);
        Route::get('{testId}', [FeatureFlagController::class, 'showTest']);
        Route::put('{testId}', [FeatureFlagController::class, 'updateTest']);
        Route::delete('{testId}', [FeatureFlagController::class, 'destroyTest']);
        Route::post('{testId}/start', [FeatureFlagController::class, 'startTest']);
        Route::post('{testId}/stop', [FeatureFlagController::class, 'stopTest']);
        Route::get('{testId}/results', [FeatureFlagController::class, 'testResults']);
        Route::post('{testId}/variants', [FeatureFlagController::class, 'addVariant']);
        Route::delete('{testId}/variants/{variantId}', [FeatureFlagController::class, 'removeVariant']);
    });

    // ─── Content Management (P8) ────────────────────────────
    Route::prefix('content')->group(function () {

        // CMS Pages
        Route::prefix('pages')->group(function () {
            Route::get('/', [ContentManagementController::class, 'listPages']);
            Route::post('/', [ContentManagementController::class, 'createPage']);
            Route::get('{pageId}', [ContentManagementController::class, 'showPage']);
            Route::put('{pageId}', [ContentManagementController::class, 'updatePage']);
            Route::delete('{pageId}', [ContentManagementController::class, 'destroyPage']);
            Route::post('{pageId}/publish', [ContentManagementController::class, 'publishPage']);
        });

        // Knowledge Base Articles
        Route::prefix('articles')->group(function () {
            Route::get('/', [ContentManagementController::class, 'listArticles']);
            Route::post('/', [ContentManagementController::class, 'createArticle']);
            Route::get('{articleId}', [ContentManagementController::class, 'showArticle']);
            Route::put('{articleId}', [ContentManagementController::class, 'updateArticle']);
            Route::delete('{articleId}', [ContentManagementController::class, 'destroyArticle']);
            Route::post('{articleId}/publish', [ContentManagementController::class, 'publishArticle']);
        });

        // Platform Announcements
        Route::prefix('announcements')->group(function () {
            Route::get('/', [ContentManagementController::class, 'listAnnouncements']);
            Route::post('/', [ContentManagementController::class, 'createAnnouncement']);
            Route::get('{announcementId}', [ContentManagementController::class, 'showAnnouncement']);
            Route::put('{announcementId}', [ContentManagementController::class, 'updateAnnouncement']);
            Route::delete('{announcementId}', [ContentManagementController::class, 'destroyAnnouncement']);
        });

        // Notification Templates
        Route::prefix('templates')->group(function () {
            Route::get('/', [ContentManagementController::class, 'listTemplates']);
            Route::post('/', [ContentManagementController::class, 'createTemplate']);
            Route::get('{templateId}', [ContentManagementController::class, 'showTemplate']);
            Route::put('{templateId}', [ContentManagementController::class, 'updateTemplate']);
            Route::delete('{templateId}', [ContentManagementController::class, 'destroyTemplate']);
            Route::post('{templateId}/toggle', [ContentManagementController::class, 'toggleTemplate']);
        });
    });

    // ─── Platform Logs & Monitoring (P9) ─────────────────────
    Route::prefix('logs')->group(function () {

        // Admin Activity Logs
        Route::prefix('activity')->group(function () {
            Route::get('/', [LogMonitoringController::class, 'listActivityLogs']);
            Route::get('{logId}', [LogMonitoringController::class, 'showActivityLog']);
        });

        // Security Alerts
        Route::prefix('security-alerts')->group(function () {
            Route::get('/', [LogMonitoringController::class, 'listSecurityAlerts']);
            Route::get('{alertId}', [LogMonitoringController::class, 'showSecurityAlert']);
            Route::post('{alertId}/resolve', [LogMonitoringController::class, 'resolveSecurityAlert']);
        });

        // Notification Logs
        Route::get('notifications', [LogMonitoringController::class, 'listNotificationLogs']);

        // Platform Events
        Route::prefix('events')->group(function () {
            Route::get('/', [LogMonitoringController::class, 'listPlatformEvents']);
            Route::post('/', [LogMonitoringController::class, 'createPlatformEvent']);
            Route::get('{eventId}', [LogMonitoringController::class, 'showPlatformEvent']);
        });

        // System Health
        Route::prefix('health')->group(function () {
            Route::get('dashboard', [LogMonitoringController::class, 'healthDashboard']);
            Route::get('checks', [LogMonitoringController::class, 'listHealthChecks']);
            Route::post('checks', [LogMonitoringController::class, 'createHealthCheck']);
        });

        // Store Health
        Route::get('store-health', [LogMonitoringController::class, 'listStoreHealth']);
    });

    // ─── Support Ticket System (P10) ─────────────────────────
    Route::prefix('support')->group(function () {

        // Tickets
        Route::prefix('tickets')->group(function () {
            Route::get('/', [SupportTicketController::class, 'listTickets']);
            Route::post('/', [SupportTicketController::class, 'createTicket']);
            Route::get('{ticketId}', [SupportTicketController::class, 'showTicket']);
            Route::put('{ticketId}', [SupportTicketController::class, 'updateTicket']);
            Route::post('{ticketId}/assign', [SupportTicketController::class, 'assignTicket']);
            Route::post('{ticketId}/status', [SupportTicketController::class, 'changeStatus']);
            Route::get('{ticketId}/messages', [SupportTicketController::class, 'listMessages']);
            Route::post('{ticketId}/messages', [SupportTicketController::class, 'addMessage']);
        });

        // Canned Responses
        Route::prefix('canned-responses')->group(function () {
            Route::get('/', [SupportTicketController::class, 'listCannedResponses']);
            Route::post('/', [SupportTicketController::class, 'createCannedResponse']);
            Route::get('{responseId}', [SupportTicketController::class, 'showCannedResponse']);
            Route::put('{responseId}', [SupportTicketController::class, 'updateCannedResponse']);
            Route::delete('{responseId}', [SupportTicketController::class, 'destroyCannedResponse']);
            Route::post('{responseId}/toggle', [SupportTicketController::class, 'toggleCannedResponse']);
        });
    });

    // ═══ P11: Marketplace Management ═══════════════════════════════
    Route::prefix('marketplace')->group(function () {
        // Store Listings
        Route::prefix('stores')->group(function () {
            Route::get('/', [MarketplaceController::class, 'listStores']);
            Route::get('{configId}', [MarketplaceController::class, 'showStore']);
            Route::put('{configId}', [MarketplaceController::class, 'updateStoreConfig']);
            Route::post('{storeId}/connect', [MarketplaceController::class, 'connectStore']);
            Route::post('{configId}/disconnect', [MarketplaceController::class, 'disconnectStore']);
        });

        // Product Listings
        Route::prefix('products')->group(function () {
            Route::get('/', [MarketplaceController::class, 'listProducts']);
            Route::get('{mappingId}', [MarketplaceController::class, 'showProduct']);
            Route::put('{mappingId}', [MarketplaceController::class, 'updateProduct']);
            Route::post('bulk-publish', [MarketplaceController::class, 'bulkPublish']);
        });

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/', [MarketplaceController::class, 'listOrders']);
            Route::get('{orderId}', [MarketplaceController::class, 'showOrder']);
        });

        // Settlements
        Route::prefix('settlements')->group(function () {
            Route::get('summary', [MarketplaceController::class, 'settlementSummary']);
            Route::get('/', [MarketplaceController::class, 'listSettlements']);
            Route::get('{settlementId}', [MarketplaceController::class, 'showSettlement']);
        });
    });

    // ─── P12  Deployment & Release Management ────────────────
    Route::prefix('deployment')->group(function () {
        Route::get('overview', [DeploymentController::class, 'platformOverview']);

        Route::prefix('releases')->group(function () {
            Route::get('/', [DeploymentController::class, 'listReleases']);
            Route::post('/', [DeploymentController::class, 'createRelease']);
            Route::get('{releaseId}', [DeploymentController::class, 'showRelease']);
            Route::put('{releaseId}', [DeploymentController::class, 'updateRelease']);
            Route::delete('{releaseId}', [DeploymentController::class, 'deleteRelease']);
            Route::post('{releaseId}/activate', [DeploymentController::class, 'activateRelease']);
            Route::post('{releaseId}/deactivate', [DeploymentController::class, 'deactivateRelease']);
            Route::put('{releaseId}/rollout', [DeploymentController::class, 'updateRollout']);
            Route::get('{releaseId}/stats', [DeploymentController::class, 'listStats']);
            Route::post('{releaseId}/stats', [DeploymentController::class, 'recordStat']);
            Route::get('{releaseId}/summary', [DeploymentController::class, 'releaseSummary']);
        });
    });

    // ─── P13  Data Management & Migration ─────────────────────
    Route::prefix('data-management')->group(function () {
        Route::get('overview', [DataManagementController::class, 'backupOverview']);

        Route::prefix('database-backups')->group(function () {
            Route::get('/', [DataManagementController::class, 'listDatabaseBackups']);
            Route::post('/', [DataManagementController::class, 'createDatabaseBackup']);
            Route::get('{backupId}', [DataManagementController::class, 'showDatabaseBackup']);
            Route::post('{backupId}/complete', [DataManagementController::class, 'completeDatabaseBackup']);
        });

        Route::prefix('backup-history')->group(function () {
            Route::get('/', [DataManagementController::class, 'listBackupHistory']);
            Route::get('{itemId}', [DataManagementController::class, 'showBackupHistoryItem']);
        });

        Route::prefix('sync-logs')->group(function () {
            Route::get('summary', [DataManagementController::class, 'syncLogSummary']);
            Route::get('/', [DataManagementController::class, 'listSyncLogs']);
            Route::get('{logId}', [DataManagementController::class, 'showSyncLog']);
        });

        Route::prefix('sync-conflicts')->group(function () {
            Route::get('/', [DataManagementController::class, 'listSyncConflicts']);
            Route::get('{conflictId}', [DataManagementController::class, 'showSyncConflict']);
            Route::post('{conflictId}/resolve', [DataManagementController::class, 'resolveSyncConflict']);
        });

        Route::prefix('provider-backup-statuses')->group(function () {
            Route::get('/', [DataManagementController::class, 'listProviderBackupStatuses']);
            Route::get('{statusId}', [DataManagementController::class, 'showProviderBackupStatus']);
        });
    });

    // ── P14  Security Center ─────────────────────────────────
    Route::prefix('security-center')->group(function () {
        Route::get('overview', [SecurityCenterController::class, 'overview']);

        Route::prefix('alerts')->group(function () {
            Route::get('/', [SecurityCenterController::class, 'listAlerts']);
            Route::get('{alertId}', [SecurityCenterController::class, 'showAlert']);
            Route::post('{alertId}/resolve', [SecurityCenterController::class, 'resolveAlert']);
        });

        Route::prefix('sessions')->group(function () {
            Route::get('/', [SecurityCenterController::class, 'listSessions']);
            Route::get('{sessionId}', [SecurityCenterController::class, 'showSession']);
            Route::post('{sessionId}/revoke', [SecurityCenterController::class, 'revokeSession']);
        });

        Route::prefix('devices')->group(function () {
            Route::get('/', [SecurityCenterController::class, 'listDevices']);
            Route::get('{deviceId}', [SecurityCenterController::class, 'showDevice']);
            Route::post('{deviceId}/wipe', [SecurityCenterController::class, 'wipeDevice']);
        });

        Route::prefix('login-attempts')->group(function () {
            Route::get('/', [SecurityCenterController::class, 'listLoginAttempts']);
            Route::get('{attemptId}', [SecurityCenterController::class, 'showLoginAttempt']);
        });

        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [SecurityCenterController::class, 'listAuditLogs']);
            Route::get('{logId}', [SecurityCenterController::class, 'showAuditLog']);
        });

        Route::prefix('policies')->group(function () {
            Route::get('/', [SecurityCenterController::class, 'listPolicies']);
            Route::get('{policyId}', [SecurityCenterController::class, 'showPolicy']);
            Route::put('{policyId}', [SecurityCenterController::class, 'updatePolicy']);
        });

        Route::prefix('ip-allowlist')->group(function () {
            Route::get('/', [SecurityCenterController::class, 'listAllowlist']);
            Route::post('/', [SecurityCenterController::class, 'createAllowlistEntry']);
            Route::delete('{entryId}', [SecurityCenterController::class, 'deleteAllowlistEntry']);
        });

        Route::prefix('ip-blocklist')->group(function () {
            Route::get('/', [SecurityCenterController::class, 'listBlocklist']);
            Route::post('/', [SecurityCenterController::class, 'createBlocklistEntry']);
            Route::delete('{entryId}', [SecurityCenterController::class, 'deleteBlocklistEntry']);
        });
    });

    // ── P15 Financial Operations ─────────────────────────────
    Route::prefix('financial-operations')->group(function () {
        Route::get('overview', [FinancialOperationsController::class, 'overview']);

        Route::prefix('payments')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'payments']);
            Route::get('{id}', [FinancialOperationsController::class, 'showPayment']);
        });

        Route::prefix('refunds')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'refunds']);
            Route::get('{id}', [FinancialOperationsController::class, 'showRefund']);
        });

        Route::prefix('cash-sessions')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'cashSessions']);
            Route::get('{id}', [FinancialOperationsController::class, 'showCashSession']);
        });

        Route::prefix('cash-events')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'cashEvents']);
            Route::get('{id}', [FinancialOperationsController::class, 'showCashEvent']);
        });

        Route::prefix('expenses')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'expenses']);
            Route::get('{id}', [FinancialOperationsController::class, 'showExpense']);
        });

        Route::prefix('gift-cards')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'giftCards']);
            Route::get('{id}', [FinancialOperationsController::class, 'showGiftCard']);
        });

        Route::get('gift-card-transactions', [FinancialOperationsController::class, 'giftCardTransactions']);

        Route::prefix('accounting-configs')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'accountingConfigs']);
            Route::get('{id}', [FinancialOperationsController::class, 'showAccountingConfig']);
        });

        Route::prefix('account-mappings')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'accountMappings']);
            Route::get('{id}', [FinancialOperationsController::class, 'showAccountMapping']);
        });

        Route::prefix('accounting-exports')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'accountingExports']);
            Route::get('{id}', [FinancialOperationsController::class, 'showAccountingExport']);
        });

        Route::prefix('auto-export-configs')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'autoExportConfigs']);
            Route::get('{id}', [FinancialOperationsController::class, 'showAutoExportConfig']);
            Route::put('{id}', [FinancialOperationsController::class, 'updateAutoExportConfig']);
        });

        Route::prefix('thawani-settlements')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'thawaniSettlements']);
            Route::get('{id}', [FinancialOperationsController::class, 'showThawaniSettlement']);
        });

        Route::prefix('thawani-orders')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'thawaniOrders']);
            Route::get('{id}', [FinancialOperationsController::class, 'showThawaniOrder']);
        });

        Route::prefix('thawani-store-configs')->group(function () {
            Route::get('/', [FinancialOperationsController::class, 'thawaniStoreConfigs']);
            Route::get('{id}', [FinancialOperationsController::class, 'showThawaniStoreConfig']);
        });

        Route::get('daily-sales-summary', [FinancialOperationsController::class, 'dailySalesSummary']);
        Route::get('product-sales-summary', [FinancialOperationsController::class, 'productSalesSummary']);
    });
});
