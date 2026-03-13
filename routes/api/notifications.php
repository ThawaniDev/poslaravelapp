<?php

use App\Domain\Notification\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notification API Routes
|--------------------------------------------------------------------------
|
| Routes for the Notification feature.
| Prefix: /api/v2/notifications
|
*/

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
