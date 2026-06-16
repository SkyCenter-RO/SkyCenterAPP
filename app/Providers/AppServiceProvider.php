<?php

namespace App\Providers;

use App\Models\LodgingReservation;
use App\Models\ParkingReservation;
use App\Models\Payment;
use App\Observers\LodgingReservationObserver;
use App\Observers\ParkingReservationObserver;
use App\Observers\PaymentObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        Payment::observe(PaymentObserver::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
