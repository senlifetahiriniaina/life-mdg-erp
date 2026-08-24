<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Inventory\Http\Controllers\Web\CategoryController;
use Modules\Inventory\Http\Controllers\Web\InventoryWebController;
use Modules\Inventory\Http\Controllers\Web\ProductController;
use Modules\Inventory\Http\Controllers\Web\WarehouseController;

// Chantier 32: matches the role gate already used by routes/api.php — this
// group previously had no role tier at all (only auth+module), so any
// authenticated user of any role could reach the same server-rendered
// pages that write/read company-scoped data, regardless of whether they
// hold an Inventory role.
Route::middleware(['auth', 'module:Inventory', 'role:employee,logistics-manager,warehouse-operator,purchasing-manager,inventory-analyst,manager,admin'])->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('dashboard');
    Route::resource('products', ProductController::class);
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');

    // Chantier 17b: templates must be editable/addable/removable, not just
    // the seeded defaults — self-contained axios-fetch modal-CRUD page,
    // same pattern as categories.index above.
    Route::get('/product-templates', fn () => Inertia::render('Inventory/ProductTemplates/Index'))->name('product-templates.index');

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

    // Chantier 21: fiche de chiffrage (BOM devis) — self-contained
    // axios-fetch list + form pages, same pattern as product-templates above.
    // /create must be registered before /{costingSheet}/edit so it isn't
    // swallowed as a numeric-looking route parameter.
    Route::get('/costing-sheets', fn () => Inertia::render('Inventory/CostingSheets/Index'))->name('costing-sheets.index');
    Route::get('/costing-sheets/create', fn () => Inertia::render('Inventory/CostingSheets/Form'))->name('costing-sheets.create');
    Route::get('/costing-sheets/{costingSheet}/edit', fn ($costingSheet) => Inertia::render('Inventory/CostingSheets/Form', ['costingSheetId' => (int) $costingSheet]))->name('costing-sheets.edit');

    // Chantier 23 (volet C): commandes de production simplifiées — page
    // self-contained list+modal-CRUD, même précédent que Categories/Index.vue.
    Route::get('/production-orders', fn () => Inertia::render('Inventory/ProductionOrders/Index'))->name('production-orders.index');

    // Chantier 24 (volet D): traçabilité bout-en-bout — self-fetch page,
    // même précédent que /stock/movements.
    Route::get('/production-orders/{productionOrder}/trace', fn ($productionOrder) => Inertia::render('Inventory/ProductionOrders/Trace', ['productionOrderId' => (int) $productionOrder]))
        ->name('production-orders.trace');
});
