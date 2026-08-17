<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\Api\SalesController;

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Sales'])->prefix('v1')->group(function () {

    // ─── Orders ────────────────────────────────────────────────────────────────
    Route::get('sales/orders', [SalesController::class, 'indexOrders'])
        ->name('sales.orders.index');

    Route::post('sales/orders', [SalesController::class, 'storeOrder'])
        ->name('sales.orders.store');

    Route::get('sales/orders/{id}', [SalesController::class, 'showOrder'])
        ->name('sales.orders.show');

    Route::put('sales/orders/{id}', [SalesController::class, 'updateOrder'])
        ->name('sales.orders.update');

    Route::put('sales/orders/{id}/status', [SalesController::class, 'updateOrderStatus'])
        ->name('sales.orders.status');

    Route::post('sales/orders/{id}/confirm', [SalesController::class, 'confirmOrder'])
        ->name('sales.orders.confirm');

    Route::post('sales/orders/{id}/cancel', [SalesController::class, 'cancelOrder'])
        ->name('sales.orders.cancel');

    // ─── Quotations ────────────────────────────────────────────────────────────
    Route::get('sales/quotations', [SalesController::class, 'indexQuotations'])
        ->name('sales.quotations.index');

    Route::post('sales/quotations', [SalesController::class, 'storeQuotation'])
        ->name('sales.quotations.store');

    Route::get('sales/quotations/{id}', [SalesController::class, 'showQuotation'])
        ->name('sales.quotations.show');

    Route::put('sales/quotations/{id}', [SalesController::class, 'updateQuotation'])
        ->name('sales.quotations.update');

    Route::post('sales/quotations/{id}/send', [SalesController::class, 'sendQuotation'])
        ->name('sales.quotations.send');

    Route::post('sales/quotations/{id}/convert', [SalesController::class, 'convertQuotation'])
        ->name('sales.quotations.convert');
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/sales')->group(function () {
    Route::post('ai/assist', [\Modules\Sales\Http\Controllers\Api\SalesAiAssistController::class, 'assist'])
        ->name('sales.ai.assist');
});
