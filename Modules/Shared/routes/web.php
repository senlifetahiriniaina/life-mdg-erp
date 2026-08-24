<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Chantier 32.8 (14-layer deep audit of Modules\Shared): `resources/js/Pages/
 * Index.vue` is a real, well-formed page (calls useAiAssistant('Shared',
 * 'view_dashboard'), a real registered AI-assist action with real fr/en
 * fallback text) — but the module had NO routes/web.php at all and its
 * RouteServiceProvider::map() only ever called mapApiRoutes(), so this page
 * was 100% unreachable from any route in the whole app, confirmed via a real
 * `php artisan route:list | grep shared` (zero web hits) before this fix —
 * the same "real page, zero route" bug class already documented and fixed
 * repeatedly elsewhere in this file (Reporting's web layer at Chantier
 * 8.5ars, Accounting's balanceSheet/incomeStatement at Chantier 18/19,
 * Payroll's Dashboard at Chantier 8.3). Gated identically to the module's
 * own routes/api.php (module:Shared + role:employee,manager,admin,
 * super-admin) — this is purely informational (lists the services this
 * module exports for other modules to consume), no per-record model to
 * authorize against, matching CountryController/CurrencyController's own
 * established "route gate is the only real check" precedent.
 */
Route::middleware(['auth', 'module:Shared', 'role:employee,manager,admin,super-admin'])->group(function () {
    Route::get('/', fn () => Inertia::render('Shared/Index'))->name('index');
});
