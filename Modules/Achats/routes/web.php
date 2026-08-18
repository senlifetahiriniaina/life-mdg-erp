<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Achats\Http\Controllers\Web\PurchaseOrderController;
use Modules\Achats\Http\Controllers\Web\RFQController;
use Modules\Achats\Http\Controllers\Web\SupplierController;

Route::middleware(['auth', 'module:Achats', 'role:purchasing-manager,warehouse-operator,manager,admin'])->group(function () {
    // Purchase Orders
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('achats.purchase-orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('achats.purchase-orders.create');
    Route::get('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('achats.purchase-orders.show');
    Route::get('/purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('achats.purchase-orders.edit');

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('achats.suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('achats.suppliers.create');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('achats.suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('achats.suppliers.edit');

    // RFQs
    Route::get('/rfqs', [RFQController::class, 'index'])->name('achats.rfqs.index');
    Route::get('/rfqs/create', [RFQController::class, 'create'])->name('achats.rfqs.create');
    Route::get('/rfqs/{rfq}', [RFQController::class, 'show'])->name('achats.rfqs.show');
    Route::get('/rfqs/{rfq}/edit', [RFQController::class, 'edit'])->name('achats.rfqs.edit');

    // SpendAnalytics/Index.vue is a self-fetching page (calls the real
    // reports/spending, reports/pending-receipts, reports/overdue-invoices
    // endpoints directly) — a plain Inertia::render() closure, matching the
    // established precedent (Inventory's stock/movements, Reporting's
    // self-fetch pages) rather than a dedicated Web controller.
    Route::get('/spend-analytics', fn () => Inertia::render('Achats/SpendAnalytics/Index'))->name('achats.spend-analytics');
});
