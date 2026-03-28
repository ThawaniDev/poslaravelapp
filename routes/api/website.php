<?php

use App\Domain\ProviderRegistration\Controllers\Api\ProviderRegistrationPublicController;
use App\Domain\Website\Controllers\Api\WebsiteFormController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Website Forms API Routes
|--------------------------------------------------------------------------
|
| Public-facing routes for website form submissions.
| These do NOT require authentication — they are submitted by
| anonymous visitors on the WameedPOS marketing website.
|
| Prefix: /api/v2/website
|
| Rate limiting is applied to prevent spam/abuse.
|
*/

Route::prefix('website')->middleware('throttle:10,1')->group(function () {
    Route::post('contact', [WebsiteFormController::class, 'submitContact']);
    Route::post('newsletter/subscribe', [WebsiteFormController::class, 'subscribeNewsletter']);
    Route::post('newsletter/unsubscribe', [WebsiteFormController::class, 'unsubscribeNewsletter']);
    Route::post('partnership', [WebsiteFormController::class, 'submitPartnership']);
    Route::post('hardware-quote', [WebsiteFormController::class, 'submitHardwareQuote']);
    Route::post('consultation', [WebsiteFormController::class, 'submitConsultation']);
    Route::post('provider-registration', [ProviderRegistrationPublicController::class, 'store']);
});
