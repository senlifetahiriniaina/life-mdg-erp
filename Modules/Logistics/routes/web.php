<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Logistics\Http\Controllers\Web\LogisticsWebController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'module:Logistics'])->group(function () {
    Route::get('/logistics', [LogisticsWebController::class, 'index'])->name('logistics.index');
    Route::get('/logistics/shipments', [LogisticsWebController::class, 'shipments'])->name('logistics.shipments.index');
    Route::get('/logistics/carriers', [LogisticsWebController::class, 'carriers'])->name('logistics.carriers.index');
    Route::get('/logistics/delivery-rounds', [LogisticsWebController::class, 'deliveryRounds'])->name('logistics.delivery_rounds.index');
    Route::get('/logistics/freight-invoices', [LogisticsWebController::class, 'freightInvoices'])->name('logistics.freight_invoices.index');
    Route::get('/logistics/customs', [LogisticsWebController::class, 'customs'])->name('logistics.customs.index');
    Route::get('/logistics/analytics', [LogisticsWebController::class, 'analytics'])->name('logistics.analytics.index');
});
