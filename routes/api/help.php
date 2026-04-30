<?php

use App\Http\Controllers\Api\Content\HelpArticlesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Help / Knowledge Base API Routes
|--------------------------------------------------------------------------
| Public endpoints — no auth required.
*/

Route::prefix('help-articles')->name('api.help-articles.')->group(function () {
    // GET /v2/help-articles?category=xxx&delivery_platform_id=yyy
    Route::get('/', [HelpArticlesController::class, 'index'])->name('index');

    // GET /v2/help-articles/{slug}
    Route::get('/{slug}', [HelpArticlesController::class, 'show'])
        ->name('show')
        ->where('slug', '[a-z0-9_-]+');
});
