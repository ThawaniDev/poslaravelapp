<?php

use App\Domain\Announcement\Controllers\Api\ProviderAnnouncementController;
use App\Domain\Notification\Controllers\Api\NotificationController;
use App\Domain\Notification\Controllers\Api\NotificationTemplateController;
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
    Route::get('/', [ProviderAnnouncementController::class, 'index']);
    Route::post('{id}/dismiss', [ProviderAnnouncementController::class, 'dismiss']);
});

// ─── Notification Templates ──────────────────────────────────
Route::prefix('notification-templates')->middleware('auth:sanctum')->group(function () {
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
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/', [NotificationController::class, 'store']);
    Route::post('batch', [NotificationController::class, 'batch']);
    Route::delete('bulk', [NotificationController::class, 'bulkDelete']);
    Route::get('unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('unread-count-by-category', [NotificationController::class, 'unreadCountByCategory']);
    Route::get('stats', [NotificationController::class, 'stats']);
    Route::put('read-all', [NotificationController::class, 'markAllAsRead']);

    // Delivery logs & stats
    Route::get('delivery-logs', [NotificationController::class, 'deliveryLogs']);
    Route::get('delivery-stats', [NotificationController::class, 'deliveryStats']);

    // Preferences (must be before {id} wildcard)
    Route::get('preferences', [NotificationController::class, 'getPreferences']);
    Route::put('preferences', [NotificationController::class, 'updatePreferences']);

    // Sound configurations
    Route::get('sound-configs', [NotificationController::class, 'getSoundConfigs']);
    Route::put('sound-configs/{eventKey}', [NotificationController::class, 'updateSoundConfig']);

    // Schedules
    Route::get('schedules', [NotificationController::class, 'listSchedules']);
    Route::post('schedules', [NotificationController::class, 'createSchedule']);
    Route::put('schedules/{id}/cancel', [NotificationController::class, 'cancelSchedule']);

    // FCM Tokens (must be before {id} wildcard)
    Route::post('fcm-tokens', [NotificationController::class, 'registerFcmToken']);
    Route::delete('fcm-tokens', [NotificationController::class, 'removeFcmToken']);

    // Single notification actions (wildcard — must be last)
    Route::put('{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('{id}', [NotificationController::class, 'destroy']);
});
