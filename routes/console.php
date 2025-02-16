<?php

use App\Console\Commands\SendInvoices;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule::call(function (Schedule $schedule) {
//     $schedule->command(command: 'send:invoices');
// })->hourly()
//     ->onSuccess(function () {
//         Log::info('Invoices were sent successfully!');
//     })
//     ->onFailure(function () {
//         Log::error('Failed to send invoices.');
//     });

Schedule::command(SendInvoices::class)->everyMinute()->onSuccess(function () {
    Log::info('Invoices were sent successfully!');
})->onFailure(function () {
    Log::error('Failed to send invoices.');
});
