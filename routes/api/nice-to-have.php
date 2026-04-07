<?php

use App\Domain\Customer\Controllers\Api\NiceToHaveController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // ─── Wishlist ─────────────────────────────────────────
    Route::prefix('wishlist')->middleware('permission:nice_to_have.manage')->group(function () {
        Route::get('/', [NiceToHaveController::class, 'wishlist']);
        Route::post('/', [NiceToHaveController::class, 'addToWishlist']);
        Route::delete('/', [NiceToHaveController::class, 'removeFromWishlist']);
    });

    // ─── Appointments ─────────────────────────────────────
    Route::prefix('appointments')->middleware('permission:nice_to_have.manage')->group(function () {
        Route::get('/', [NiceToHaveController::class, 'appointments']);
        Route::post('/', [NiceToHaveController::class, 'createAppointment']);
        Route::put('/{id}', [NiceToHaveController::class, 'updateAppointment']);
        Route::post('/{id}/cancel', [NiceToHaveController::class, 'cancelAppointment']);
    });

    // ─── CFD ──────────────────────────────────────────────
    Route::prefix('cfd')->middleware('permission:pos_customization.manage')->group(function () {
        Route::get('/config', [NiceToHaveController::class, 'cfdConfig']);
        Route::put('/config', [NiceToHaveController::class, 'updateCfdConfig']);
    });

    // ─── Gift Registry ────────────────────────────────────
    Route::prefix('gift-registry')->middleware('permission:nice_to_have.manage')->group(function () {
        Route::get('/', [NiceToHaveController::class, 'registries']);
        Route::post('/', [NiceToHaveController::class, 'createRegistry']);
        Route::get('/share/{code}', [NiceToHaveController::class, 'registryByShareCode']);
        Route::post('/{registryId}/items', [NiceToHaveController::class, 'addRegistryItem']);
        Route::get('/{registryId}/items', [NiceToHaveController::class, 'registryItems']);
    });

    // ─── Digital Signage ──────────────────────────────────
    Route::prefix('signage')->middleware('permission:pos_customization.manage')->group(function () {
        Route::get('/playlists', [NiceToHaveController::class, 'playlists']);
        Route::post('/playlists', [NiceToHaveController::class, 'createPlaylist']);
        Route::put('/playlists/{id}', [NiceToHaveController::class, 'updatePlaylist']);
        Route::delete('/playlists/{id}', [NiceToHaveController::class, 'deletePlaylist']);
    });

    // ─── Gamification ─────────────────────────────────────
    Route::prefix('gamification')->middleware('permission:nice_to_have.view')->group(function () {
        Route::get('/challenges', [NiceToHaveController::class, 'challenges']);
        Route::get('/badges', [NiceToHaveController::class, 'badges']);
        Route::get('/tiers', [NiceToHaveController::class, 'tiers']);
        Route::get('/customer/{customerId}/progress', [NiceToHaveController::class, 'customerProgress']);
        Route::get('/customer/{customerId}/badges', [NiceToHaveController::class, 'customerBadges']);
    });
});
