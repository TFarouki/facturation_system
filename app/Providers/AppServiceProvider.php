<?php

namespace App\Providers;

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
        \App\Models\Category::observe(\App\Observers\UserTrackingObserver::class);
        \App\Models\Product::observe(\App\Observers\UserTrackingObserver::class);
        \App\Models\Unit::observe(\App\Observers\UserTrackingObserver::class);
        \App\Models\Supplier::observe(\App\Observers\UserTrackingObserver::class);
        \App\Models\PurchaseInvoice::observe(\App\Observers\UserTrackingObserver::class);
        \App\Models\PurchaseDetail::observe(\App\Observers\UserTrackingObserver::class);
        // Setting model doesn't have user tracking columns, so no observer needed
        \App\Models\User::observe(\App\Observers\UserTrackingObserver::class);
    }
}
