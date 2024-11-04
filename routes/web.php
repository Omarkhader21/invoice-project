<?php

use App\Http\Controllers\Invoice\InvoiceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OdbcController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-odbc', [OdbcController::class, 'testOdbcConnection']);

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::resource('/invoice', InvoiceController::class)->only(['index', 'show']);
});
