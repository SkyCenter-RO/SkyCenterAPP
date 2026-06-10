<?php

use App\Http\Controllers\Api\Automation\ParkingReservationWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('automation.token')->prefix('automation')->group(function (): void {
    Route::post('parking-reservations', ParkingReservationWebhookController::class);
});
