<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Inventory\Http\Controllers\Web\CategoryController;
use Modules\Inventory\Http\Controllers\Web\InventoryWebController;
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

    // Chantier 16: cash/bank-import-style preview→commit page for stock in/out —
    // self-contained axios-fetch page (warehouse picker + file upload + preview
    // table), same Inertia::render() closure pattern as stock-movements above.
    Route::get('/stock/import', fn () => Inertia::render('Inventory/Stock/Import'))->name('stock-import');
    Route::get('/reorder-automation', fn () => Inertia::render('Inventory/ReorderAutomation/Index'))->name('reorder-automation');
    Route::get('/demand-forecast', fn () => Inertia::render('Inventory/DemandForecast/Index'))->name('demand-forecast');
    Route::get('/marketplace-sync', fn () => Inertia::render('Inventory/MarketplaceSync/Index'))->name('marketplace-sync');

    // Chantier 8.3: InventoryWebController's 8 methods were real (correct props matching
    // their pages' defineProps exactly) but had zero routes anywhere. 4 of the 8 target
    // pages (Shipments, Returns, WMS/Crossdock, WMS/Waves) turned out to be fully
    // self-contained axios-fetch pages that never read server props at all — routed those
    // via a plain Inertia::render() like stock/movements above rather than running the
    // controller's real (but wasted) queries against them.
    Route::get('/suppliers', [InventoryWebController::class, 'suppliers'])->name('suppliers.index');
    Route::get('/purchase-orders', [InventoryWebController::class, 'purchaseOrders'])->name('purchase-orders.index');
    Route::get('/wms/picking', [InventoryWebController::class, 'picking'])->name('wms.picking');
    Route::get('/cycle-counts', [InventoryWebController::class, 'cycleCounts'])->name('cycle-counts.index');
    Route::get('/shipments', fn () => Inertia::render('Inventory/Shipments/Index'))->name('shipments.index');
    Route::get('/returns', fn () => Inertia::render('Inventory/Returns/Index'))->name('returns.index');
    Route::get('/wms/crossdock', fn () => Inertia::render('Inventory/WMS/Crossdock/Index'))->name('wms.crossdock');
    Route::get('/wms/waves', fn () => Inertia::render('Inventory/WMS/Waves/Index'))->name('wms.waves');

    // Chantier 8.3: ChannelController (marketplace channel connections) was real and
    // routed at the API layer above with no page at all — new self-contained page.
    Route::get('/channels', fn () => Inertia::render('Inventory/Channels/Index'))->name('channels.index');

    // Chantier 17: self-contained axios-fetch page (product picker + external
    // price observations + internal-cost comparison), same Inertia::render()
    // closure pattern as stock/movements above. Linked from Products/Show.vue
    // ("Comparer les prix") with an optional ?product_id= preselection.
    Route::get('/benchmark', fn () => Inertia::render('Inventory/Benchmark/Index'))->name('benchmark.index');
});
