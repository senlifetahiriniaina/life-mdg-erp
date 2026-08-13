<?php

use Illuminate\Support\Facades\Route;
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
});
