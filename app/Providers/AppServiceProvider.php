<?php

namespace App\Providers;

use App\OdbcConnector;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $this->app->singleton(\App\Services\InvoiceFileService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::extend('odbc', function ($config) {
            $connector = new OdbcConnector();
            $pdo = $connector->connect($config);

            // Pass an empty string for the database parameter since ODBC doesn't need it
            return new \Illuminate\Database\Connection($pdo, '', '', $config);
        });
    }
}
