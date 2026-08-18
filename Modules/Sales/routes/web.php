<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Sales module is primarily API-driven — SalesIndex.vue is a real,
// self-fetching page (calls GET /api/v1/sales/orders directly), it only
// needed a thin route to be reachable, matching the established
// "self-fetch page → plain closure route" precedent used throughout this
// repo (Inventory's stock/movements, Achats' spend-analytics).
Route::middleware(['web', 'auth', 'module:Sales'])->group(function () {
    Route::get('/sales', fn () => Inertia::render('Sales/SalesIndex'))->name('sales.index');
});
