<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Integration Web Routes (Inertia)
|--------------------------------------------------------------------------
|
| Chantier 8.6: this module had no routes/web.php at all — its real,
| fully-built IntegrationsIndex.vue page (connectors + catalogue tabs,
| self-fetching via /api/v1/integration/*) was unreachable from the app.
*/

Route::middleware(['auth', 'module:Integration'])->group(function () {
    Route::get('/integration', fn () => Inertia::render('Integration/IntegrationsIndex'))
        ->name('integration.index');
});
