<?php

namespace App\Providers;

use App\OdbcConnector;
use Illuminate\Support\Facades\DB;
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
        DB::extend('odbc', function ($config) {
            $connector = new OdbcConnector();
            $pdo = $connector->connect($config);

            // Pass an empty string for the database parameter since ODBC doesn't need it
            return new \Illuminate\Database\Connection($pdo, '', '', $config);
        });
    }
}
