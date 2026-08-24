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

    // Chantier 22 (volet B): SalesIndex.vue's own viewOrder() already
    // navigates to /sales/orders/{id} (router.visit) with no route behind
    // it at all — a real, previously-undocumented dead-link bug, closed
    // here as a side effect of building the order-detail page the
    // deposit/balance cycle needed anyway. Self-fetching page (GET
    // /api/v1/sales/orders/{id}), same closure pattern as the rest of
    // this repo's self-fetch routes.
    // Chantier 32 (volet B) — SalesIndex.vue's createOrder()/editOrder()
    // already navigate here (a second, previously-undocumented dead-link
    // bug alongside the one already fixed at Chantier 22 for viewOrder());
    // registered BEFORE the {id} wildcard route below so 'create' isn't
    // swallowed by it.
    Route::get('/sales/orders/create', fn () => Inertia::render('Sales/Orders/Create'))
        ->name('sales.orders.create');

    Route::get('/sales/orders/{id}', fn ($id) => Inertia::render('Sales/Orders/Show', ['orderId' => (int) $id]))
        ->name('sales.orders.show');

    Route::get('/sales/orders/{id}/edit', fn ($id) => Inertia::render('Sales/Orders/Edit', ['orderId' => (int) $id]))
        ->name('sales.orders.edit');

    // Chantier 25 (volet E) — commandes récurrentes, self-fetch page,
    // même précédent que SalesIndex.vue.
    Route::get('/sales/recurring-orders', fn () => Inertia::render('Sales/RecurringOrders/Index'))
        ->name('sales.recurring-orders.index');

    // Chantier 26 (volet B) — objectifs commerciaux assistés par IA,
    // self-fetch page, même précédent que SalesIndex.vue.
    Route::get('/sales/objectives', fn () => Inertia::render('Sales/Objectives/Index'))
        ->name('sales.objectives.index');
});
