<?php

namespace App\Providers;

use App\OdbcConnector;
use App\Services\LicenseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register InvoiceService as a singleton
        $this->app->singleton(\App\Services\InvoiceService::class, \App\Services\InvoiceXmlService::class);

        // Register InvoiceFileService as a singleton
        $this->app->singleton(\App\Services\InvoiceFileService::class, \App\Services\QrcodeService::class);

        // Register \App\Services\LicenseService::class 
        $this->app->singleton(LicenseService::class, function ($app) {
            return new LicenseService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
