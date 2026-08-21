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

    // Chantier 32.16 (Sales deep 14-layer audit): SalesIndex.vue's
    // "Nouvelle commande"/pencil-edit buttons have always navigated to
    // /sales/orders/create and /sales/orders/{id}/edit — neither route ever
    // existed (confirmed via a real HTTP request returning 404 before this
    // fix), a dead-link bug already flagged as a known, deliberately
    // deferred gap in this file's own Chantier 22/25 history. Registered
    // BEFORE the {id} show route below — both are 3-segment routes, and
    // Laravel's first-registered-wins on an exact literal-vs-wildcard
    // collision (same precedent already documented elsewhere in this app
    // for the Helpdesk KB-routes double-registration bug).
    Route::get('/sales/orders/create', fn () => Inertia::render('Sales/Orders/Create'))
        ->name('sales.orders.create');

    // Chantier 22 (volet B): SalesIndex.vue's own viewOrder() already
    // navigates to /sales/orders/{id} (router.visit) with no route behind
    // it at all — a real, previously-undocumented dead-link bug, closed
    // here as a side effect of building the order-detail page the
    // deposit/balance cycle needed anyway. Self-fetching page (GET
    // /api/v1/sales/orders/{id}), same closure pattern as the rest of
    // this repo's self-fetch routes.
    Route::get('/sales/orders/{id}', fn ($id) => Inertia::render('Sales/Orders/Show', ['orderId' => (int) $id]))
        ->name('sales.orders.show');

    // Chantier 32.16 — see the /sales/orders/create comment above.
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
