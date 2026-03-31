<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Wameed POS
|--------------------------------------------------------------------------
|
| Each feature has its own route file in routes/api/.
| They are auto-loaded here with the /api/v2 prefix.
|
*/

Route::prefix('v2')->group(function () {
    // Load all feature route files from routes/api/
    $apiRouteFiles = glob(base_path('routes/api/*.php'));
    foreach ($apiRouteFiles as $routeFile) {
        require $routeFile;
    }
});

// Health check
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
    'version' => '1.0.0',
    'timestamp' => now()->toIso8601String(),
]));
