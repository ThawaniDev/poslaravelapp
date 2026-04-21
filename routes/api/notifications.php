<?php

use App\Domain\Announcement\Controllers\Api\ProviderAnnouncementController;
use App\Domain\Announcement\Controllers\Api\ProviderPaymentReminderController;
use App\Domain\AppUpdateManagement\Controllers\Api\ProviderAppReleaseController;
use App\Domain\Notification\Controllers\Api\NotificationController;
use App\Domain\Notification\Controllers\Api\NotificationTemplateController;
use App\Http\Controllers\Api\MaintenanceStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notification & Announcement API Routes
|--------------------------------------------------------------------------
|
| Routes for the Notification and Announcement features.
| Prefixes: /api/v2/notifications, /api/v2/announcements, /api/v2/notification-templates
|
*/

// ─── Provider Announcements ──────────────────────────────────
Route::prefix('announcements')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProviderAnnouncementController::class, 'index'])->middleware('permission:notifications.view');
    Route::post('{id}/dismiss', [ProviderAnnouncementController::class, 'dismiss'])->middleware('permission:notifications.view');
});

// ─── Provider Payment Reminders ─────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('payment-reminders', [ProviderPaymentReminderController::class, 'index'])
        ->middleware('permission:notifications.view');
});

// ─── Provider App Releases (notification centre tab) ────────
Route::middleware('auth:sanctum')->prefix('app-releases')->group(function () {
    Route::get('/', [ProviderAppReleaseController::class, 'index'])
        ->middleware('permission:auto_update.view');
    Route::get('latest', [ProviderAppReleaseController::class, 'latest'])
        ->middleware('permission:auto_update.view');
});

// ─── Maintenance Mode Status (no auth required so banner can show on login screen) ─
Route::get('maintenance-status', [MaintenanceStatusController::class, 'show']);

// ─── Notification Templates ──────────────────────────────────
Route::prefix('notification-templates')->middleware(['auth:sanctum', 'permission:notifications.manage'])->group(function () {
    // Static routes first (before wildcard)
    Route::get('events', [NotificationTemplateController::class, 'events']);
    Route::get('events/{eventKey}', [NotificationTemplateController::class, 'eventVariables'])
        ->where('eventKey', '[a-z_.]+');
    Route::post('render', [NotificationTemplateController::class, 'render']);
    Route::post('dispatch', [NotificationTemplateController::class, 'dispatch']);

    // CRUD
    Route::get('/', [NotificationTemplateController::class, 'index']);
    Route::get('{id}', [NotificationTemplateController::class, 'show']);
});

Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
    // Notifications listing and creation
    Route::get('/', [NotificationController::class, 'index'])->middleware('permission:notifications.view');
    Route::post('/', [NotificationController::class, 'store'])->middleware('permission:notifications.manage');
    Route::post('batch', [NotificationController::class, 'batch'])->middleware('permission:notifications.manage');
    Route::delete('bulk', [NotificationController::class, 'bulkDelete'])->middleware('permission:notifications.manage');
    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->middleware('permission:notifications.view');
    Route::get('unread-count-by-category', [NotificationController::class, 'unreadCountByCategory'])->middleware('permission:notifications.view');
    Route::get('stats', [NotificationController::class, 'stats'])->middleware('permission:notifications.view');
    Route::put('read-all', [NotificationController::class, 'markAllAsRead'])->middleware('permission:notifications.view');

    // Delivery logs & stats
    Route::get('delivery-logs', [NotificationController::class, 'deliveryLogs'])->middleware('permission:notifications.manage');
    Route::get('delivery-stats', [NotificationController::class, 'deliveryStats'])->middleware('permission:notifications.manage');

    // Preferences (must be before {id} wildcard)
    Route::get('preferences', [NotificationController::class, 'getPreferences'])->middleware('permission:notifications.manage');
    Route::put('preferences', [NotificationController::class, 'updatePreferences'])->middleware('permission:notifications.manage');

    // Sound configurations
    Route::get('sound-configs', [NotificationController::class, 'getSoundConfigs'])->middleware('permission:notifications.manage');
    Route::put('sound-configs/{eventKey}', [NotificationController::class, 'updateSoundConfig'])->middleware('permission:notifications.manage');

    // Schedules
    Route::get('schedules', [NotificationController::class, 'listSchedules'])->middleware('permission:notifications.schedules');
    Route::post('schedules', [NotificationController::class, 'createSchedule'])->middleware('permission:notifications.schedules');
    Route::put('schedules/{id}/cancel', [NotificationController::class, 'cancelSchedule'])->middleware('permission:notifications.schedules');

    // FCM Tokens (must be before {id} wildcard)
    Route::post('fcm-tokens', [NotificationController::class, 'registerFcmToken'])->middleware('permission:notifications.view');
    Route::delete('fcm-tokens', [NotificationController::class, 'removeFcmToken'])->middleware('permission:notifications.view');

    // Single notification actions (wildcard — must be last)
    Route::put('{id}/read', [NotificationController::class, 'markAsRead'])->middleware('permission:notifications.view');
    Route::delete('{id}', [NotificationController::class, 'destroy'])->middleware('permission:notifications.manage');
});
