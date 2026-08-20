<?php

use Illuminate\Support\Facades\Route;
use Modules\Achats\Http\Controllers\Api\PurchaseOrderController;
use Modules\Achats\Http\Controllers\Api\PurchaseOrderExportController;
use Modules\Achats\Http\Controllers\Api\PurchaseOrderLineController;
use Modules\Achats\Http\Controllers\Api\PurchaseReceiptController;
use Modules\Achats\Http\Controllers\Api\PurchaseReportsController;
use Modules\Achats\Http\Controllers\Api\RFQController;
use Modules\Achats\Http\Controllers\Api\RFQLineController;
use Modules\Achats\Http\Controllers\Api\SupplierController;
use Modules\Achats\Http\Controllers\Api\SupplierQuoteController;

// Default: Simple GET throttle (1000 req/min)
// Chantier 10: this group had a role: gate but no module:Achats gate at all
// — unlike Inventory/Logistics's own main groups and unlike Achats' own
// routes/web.php (which already got module:Achats in Chantier 8.5-light) —
// so a tenant with the Achats module disabled could still reach its full
// API. Added to match every sibling module's main route group.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Achats', 'role:purchasing-manager,warehouse-operator,manager,admin', 'throttle:simple_get'])->group(function () {
    // Purchase Orders
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show']);

    // PO Export
    Route::get('purchase-orders/{purchase_order}/export/json', [PurchaseOrderExportController::class, 'exportJson']);
    Route::get('purchase-orders/{purchase_order}/export/csv', [PurchaseOrderExportController::class, 'exportCsv']);
    Route::get('purchase-orders/{purchase_order}/summary', [PurchaseOrderExportController::class, 'summary']);

    // PO Lines (nested)
    Route::get('purchase-orders/{purchase_order}/lines', [PurchaseOrderLineController::class, 'index']);
    Route::get('purchase-orders/{purchase_order}/lines/{line}', [PurchaseOrderLineController::class, 'show']);

    // Suppliers
    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
    Route::get('suppliers/{supplier}/performance', [SupplierController::class, 'performanceMetrics']);
    Route::get('suppliers/{supplier}/quotes', [SupplierController::class, 'quoteHistory']);

    // RFQs
    Route::get('rfqs', [RFQController::class, 'index']);
    Route::get('rfqs/{rfq}', [RFQController::class, 'show']);
    Route::get('rfqs/{rfq}/comparison', [RFQController::class, 'comparison']);

    // RFQ Lines (nested)
    Route::get('rfqs/{rfq}/lines', [RFQLineController::class, 'index']);
    Route::get('rfqs/{rfq}/lines/{line}', [RFQLineController::class, 'show']);

    // Supplier Quotes
    Route::get('supplier-quotes', [SupplierQuoteController::class, 'index']);
    Route::get('supplier-quotes/{supplier_quote}', [SupplierQuoteController::class, 'show']);

    // Purchase Receipts
    Route::get('purchase-receipts', [PurchaseReceiptController::class, 'index']);
    Route::get('purchase-receipts/{purchase_receipt}', [PurchaseReceiptController::class, 'show']);

    // Reports (complex analytics)
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('reports/spending', [PurchaseReportsController::class, 'spending']);
        Route::get('reports/pending-receipts', [PurchaseReportsController::class, 'pendingReceipts']);
        Route::get('reports/overdue-invoices', [PurchaseReportsController::class, 'overdueInvoices']);
    });

    // Mutations
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
        // Chantier 10: PurchaseOrders/Form.vue's edit mode sends PATCH, not
        // PUT — this route only ever registered PUT (405 on every real edit
        // submission, undetected since no existing test exercised the edit
        // path at all). Same bug found and fixed on suppliers/rfqs/
        // purchase-receipts below.
        Route::match(['put', 'patch'], 'purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update']);
        Route::delete('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'destroy']);
        Route::post('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submitForApproval']);
        Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('purchase-orders/{purchase_order}/reject', [PurchaseOrderController::class, 'reject']);
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);

        // Chantier 22 (volet B) — cycle acompte/solde.
        Route::post('purchase-orders/{purchase_order}/deposit/request', [PurchaseOrderController::class, 'requestDeposit']);
        Route::post('purchase-orders/{purchase_order}/deposit/pay', [PurchaseOrderController::class, 'payDeposit']);
        Route::post('purchase-orders/{purchase_order}/balance/request', [PurchaseOrderController::class, 'requestBalance']);
        Route::post('purchase-orders/{purchase_order}/balance/pay', [PurchaseOrderController::class, 'payBalance']);

        Route::post('purchase-orders/{purchase_order}/lines', [PurchaseOrderLineController::class, 'store']);
        Route::put('purchase-orders/{purchase_order}/lines/{line}', [PurchaseOrderLineController::class, 'update']);
        Route::delete('purchase-orders/{purchase_order}/lines/{line}', [PurchaseOrderLineController::class, 'destroy']);

        Route::post('suppliers', [SupplierController::class, 'store']);
        // Chantier 10: Suppliers/Form.vue also sends PATCH on edit — same fix.
        Route::match(['put', 'patch'], 'suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);

        Route::post('rfqs', [RFQController::class, 'store']);
        // Chantier 10: RFQs/Form.vue also sends PATCH on edit — same fix.
        Route::match(['put', 'patch'], 'rfqs/{rfq}', [RFQController::class, 'update']);
        Route::delete('rfqs/{rfq}', [RFQController::class, 'destroy']);
        Route::post('rfqs/{rfq}/issue', [RFQController::class, 'issue']);
        Route::post('rfqs/{rfq}/close', [RFQController::class, 'closeRfq']);

        Route::post('rfqs/{rfq}/lines', [RFQLineController::class, 'store']);
        Route::put('rfqs/{rfq}/lines/{line}', [RFQLineController::class, 'update']);
        Route::delete('rfqs/{rfq}/lines/{line}', [RFQLineController::class, 'destroy']);

        Route::post('rfqs/{rfq}/suppliers/{supplier}/quote', [SupplierQuoteController::class, 'store']);
        Route::post('supplier-quotes/{supplier_quote}/accept', [SupplierQuoteController::class, 'accept']);
        Route::post('supplier-quotes/{supplier_quote}/reject', [SupplierQuoteController::class, 'reject']);

        Route::post('purchase-receipts', [PurchaseReceiptController::class, 'store']);
        // Chantier 10: PurchaseReceipts/Form.vue also sends PATCH on edit — same fix.
        Route::match(['put', 'patch'], 'purchase-receipts/{purchase_receipt}', [PurchaseReceiptController::class, 'update']);
        Route::delete('purchase-receipts/{purchase_receipt}', [PurchaseReceiptController::class, 'destroy']);
        Route::post('purchase-orders/{purchase_order}/receipts', [PurchaseReceiptController::class, 'storeForOrder']);
        Route::post('purchase-receipts/{purchase_receipt}/complete', [PurchaseReceiptController::class, 'complete']);
        Route::post('purchase-receipts/{purchase_receipt}/quality-issue', [PurchaseReceiptController::class, 'recordQualityIssue']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
// Chantier 10: this group had zero module:/role: gate at all — same finding
// as Logistics' own ai/assist route — so any authenticated user of any
// module/role could reach it. Matched to the main group's gate above.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Achats', 'role:purchasing-manager,warehouse-operator,manager,admin'])->prefix('v1/achats')->group(function () {
    Route::post('ai/assist', [\Modules\Achats\Http\Controllers\Api\AchatsAiAssistController::class, 'assist'])
        ->name('achats.ai.assist');
});
