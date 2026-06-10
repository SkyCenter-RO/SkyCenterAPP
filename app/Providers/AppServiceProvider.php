<?php

namespace App\Providers;

use App\Models\LodgingReservation;
use App\Models\ParkingReservation;
use App\Observers\LodgingReservationObserver;
use App\Observers\ParkingReservationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ParkingReservation::observe(ParkingReservationObserver::class);
        LodgingReservation::observe(LodgingReservationObserver::class);
    }
}
