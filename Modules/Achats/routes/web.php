<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Achats\Http\Controllers\Web\PurchaseOrderController;
use Modules\Achats\Http\Controllers\Web\PurchaseReceiptController;
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

    // Chantier 10: RFQs/Show.vue's goToComparison() and RFQs/Index.vue's
    // evaluateQuotes() both real, already-wired navigation triggers
    // (window.location.href = `/rfqs/${id}/compare`) with no route behind
    // them at all — a 404 on every click. Compare.vue is a real,
    // self-fetching page (GET rfqs/{id}, already real), so a plain closure
    // matches the established stock/movements/spend-analytics precedent.
    Route::get('/rfqs/{rfq}/compare', fn () => Inertia::render('Achats/RFQs/Compare'))->name('achats.rfqs.compare');

    // Purchase Receipts (Chantier 10 — see PurchaseReceiptController's docblock)
    Route::get('/purchase-receipts', [PurchaseReceiptController::class, 'index'])->name('achats.purchase-receipts.index');
    Route::get('/purchase-receipts/create', [PurchaseReceiptController::class, 'create'])->name('achats.purchase-receipts.create');
    Route::get('/purchase-receipts/{purchase_receipt}', [PurchaseReceiptController::class, 'show'])->name('achats.purchase-receipts.show');
    Route::get('/purchase-receipts/{purchase_receipt}/edit', [PurchaseReceiptController::class, 'edit'])->name('achats.purchase-receipts.edit');

    // SpendAnalytics/Index.vue is a self-fetching page (calls the real
    // reports/spending, reports/pending-receipts, reports/overdue-invoices
    // endpoints directly) — a plain Inertia::render() closure, matching the
    // established precedent (Inventory's stock/movements, Reporting's
    // self-fetch pages) rather than a dedicated Web controller.
    Route::get('/spend-analytics', fn () => Inertia::render('Achats/SpendAnalytics/Index'))->name('achats.spend-analytics');
});
