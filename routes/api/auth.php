<?php

use App\Domain\Auth\Controllers\Api\LoginController;
use App\Domain\Auth\Controllers\Api\OtpController;
use App\Domain\Auth\Controllers\Api\ProfileController;
use App\Domain\Auth\Controllers\Api\RegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth API Routes
|--------------------------------------------------------------------------
|
| Routes for the Auth feature.
| Prefix: /api/v2/auth
|
*/

Route::prefix('auth')->group(function () {

    // ─── Public (no auth required) ────────────────────────────────
    Route::post('/register', RegisterController::class)
        ->name('auth.register');

    Route::post('/login', [LoginController::class, 'login'])
        ->name('auth.login');

    Route::post('/login/pin', [LoginController::class, 'loginByPin'])
        ->name('auth.login.pin');

    Route::post('/otp/send', [OtpController::class, 'send'])
        ->name('auth.otp.send');

    Route::post('/otp/verify', [OtpController::class, 'verify'])
        ->name('auth.otp.verify');

    // ─── Authenticated ────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        // Profile
        Route::get('/me', [ProfileController::class, 'me'])
            ->name('auth.me');

        Route::put('/profile', [ProfileController::class, 'update'])
            ->name('auth.profile.update');

        Route::put('/password', [ProfileController::class, 'changePassword'])
            ->name('auth.password.change');

        Route::put('/pin', [ProfileController::class, 'setPin'])
            ->name('auth.pin.set');

        // Token
        Route::post('/refresh', [ProfileController::class, 'refreshToken'])
            ->name('auth.token.refresh');

        // Logout
        Route::post('/logout', [LoginController::class, 'logout'])
            ->name('auth.logout');

        Route::post('/logout-all', [LoginController::class, 'logoutAll'])
            ->name('auth.logout.all');
    });
});
