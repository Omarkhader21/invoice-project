<?php

namespace App\Providers;

use App\OdbcConnector;
use App\Services\LicenseService;
use App\Services\QrcodeService;
use App\Services\SalesInvoiceXmlService;
use App\Services\IncomeInvoiceXmlService;
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
        // Register InvoiceService as a singleton (assuming it remains relevant)
        $this->app->singleton(\App\Services\InvoiceService::class, \App\Services\InvoiceService::class);

        // Register SalesInvoiceXmlService as a singleton
        $this->app->singleton(\App\Services\SalesInvoiceXmlService::class, function ($app) {
            return new SalesInvoiceXmlService();
        });

        // Register IncomeInvoiceXmlService as a singleton
        $this->app->singleton(\App\Services\IncomeInvoiceXmlService::class, function ($app) {
            return new IncomeInvoiceXmlService();
        });

        // Register InvoiceFileService as a singleton
        $this->app->singleton(\App\Services\InvoiceFileService::class);

        // Register QrcodeService as a singleton
        $this->app->singleton(QrcodeService::class, function ($app) {
            return new QrcodeService();
        });

        // Register LicenseService as a singleton
        $this->app->singleton(LicenseService::class, function ($app) {
            return new LicenseService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set a global variable for the invoice type from the .env file
        $invoiceType = env('INVOICE_TYPE', 'sales'); // Default to 'sales' if not set
        Config::set('app.invoice_type', $invoiceType);
    }
}
