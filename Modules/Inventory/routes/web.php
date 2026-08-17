<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Inventory\Http\Controllers\Web\CategoryController;
use Modules\Inventory\Http\Controllers\Web\ProductController;
use Modules\Inventory\Http\Controllers\Web\StockAdjustmentController;
use Modules\Inventory\Http\Controllers\Web\WarehouseController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('dashboard');
    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('warehouses', WarehouseController::class);
    Route::resource('stock-adjustments', StockAdjustmentController::class);

    Route::get('/stock/movements', fn () => Inertia::render('Inventory/Stock/Movements'))->name('stock-movements');
    Route::get('/reorder-automation', fn () => Inertia::render('Inventory/ReorderAutomation/Index'))->name('reorder-automation');
    Route::get('/demand-forecast', fn () => Inertia::render('Inventory/DemandForecast/Index'))->name('demand-forecast');
    Route::get('/marketplace-sync', fn () => Inertia::render('Inventory/MarketplaceSync/Index'))->name('marketplace-sync');
});
