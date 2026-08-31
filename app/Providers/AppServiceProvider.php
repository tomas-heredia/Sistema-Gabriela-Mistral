<?php

namespace App\Providers;

use App\Cobranzas\Models\Observers\PagoCuotaObserver;
use App\Cobranzas\Models\PagoCuota;
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
        PagoCuota::observe(PagoCuotaObserver::class);
    }
}
