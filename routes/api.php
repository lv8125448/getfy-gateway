<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1) – Payment API
|--------------------------------------------------------------------------
|
| Authenticated by API key (Bearer or X-API-Key). Middleware resolves
| ApiApplication and attaches it to the request.
|
*/

Route::middleware(['api.application', 'throttle:api'])->prefix('v1')->group(function () {
    // Checkout Pro: create hosted checkout session
    Route::post('checkout/sessions', [\App\Http\Controllers\Api\V1\CheckoutSessionsController::class, 'store'])
        ->name('api.v1.checkout.sessions.store');

    // Checkout Transparente: create payment (by method)
    Route::post('payments/pix', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'createPix'])
        ->name('api.v1.payments.pix');
    Route::post('payments/card', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'createCard'])
        ->name('api.v1.payments.card');
    Route::post('payments/boleto', [\App\Http\Controllers\Api\V1\PaymentsController::class, 'createBoleto'])
        ->name('api.v1.payments.boleto');

    // Get payment/order status
    Route::get('payments/{order}', [\App\Http\Controllers\Api\V1\PaymentStatusController::class, 'show'])
        ->name('api.v1.payments.show');
});
