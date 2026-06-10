<?php

use App\Http\Controllers\Api\Automation\DispatchReviewRequestsController;
use App\Http\Controllers\Api\Automation\LodgingReservationWebhookController;
use App\Http\Controllers\Api\Automation\ParkingReservationWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('automation.token')->prefix('automation')->group(function (): void {
    Route::post('parking-reservations', ParkingReservationWebhookController::class);
    Route::post('lodging-reservations', LodgingReservationWebhookController::class);
    Route::post('dispatch-review-requests', DispatchReviewRequestsController::class);
});
