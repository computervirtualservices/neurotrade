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
        // e.g. in AppServiceProvider::boot() or right before your MACD call:
        ini_set('precision',       14);
        ini_set('serialize_precision', -1);
    }
}
