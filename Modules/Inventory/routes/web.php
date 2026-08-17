<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Inventory\Http\Controllers\Web\CategoryController;
use Modules\Inventory\Http\Controllers\Web\ProductController;
use Modules\Inventory\Http\Controllers\Web\WarehouseController;

Route::middleware(['auth', 'module:Inventory'])->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('dashboard');
    Route::resource('products', ProductController::class);
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');

    // Chantier 8.3: stock-adjustments was a 100%-redundant scaffold (Index/Form/Show.vue
    // never existed) — Stock/Movements.vue below already covers manual adjustments via
    // POST stock-movements {type: 'adjustment'}, so it was deleted rather than built.
    Route::get('/stock/movements', fn () => Inertia::render('Inventory/Stock/Movements'))->name('stock-movements');
    Route::get('/reorder-automation', fn () => Inertia::render('Inventory/ReorderAutomation/Index'))->name('reorder-automation');
    Route::get('/demand-forecast', fn () => Inertia::render('Inventory/DemandForecast/Index'))->name('demand-forecast');
    Route::get('/marketplace-sync', fn () => Inertia::render('Inventory/MarketplaceSync/Index'))->name('marketplace-sync');
});
