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
    Route::get('unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('read-all', [NotificationController::class, 'markAllAsRead']);

    // Preferences (must be before {id} wildcard)
    Route::get('preferences', [NotificationController::class, 'getPreferences']);
    Route::put('preferences', [NotificationController::class, 'updatePreferences']);

    // FCM Tokens (must be before {id} wildcard)
    Route::post('fcm-tokens', [NotificationController::class, 'registerFcmToken']);
    Route::delete('fcm-tokens', [NotificationController::class, 'removeFcmToken']);

    // Single notification actions (wildcard — must be last)
    Route::put('{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('{id}', [NotificationController::class, 'destroy']);
});
