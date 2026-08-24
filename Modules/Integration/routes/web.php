<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Integration\Http\Controllers\Api\WhbFederationController;

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

// Chantier 32.6: WhbFederationController::wellKnown() has existed since
// this controller was first built (Chantier 8.6's FederationReceiverTest.php
// docblock already documents WhbFederationService::discover() calling
// `GET {serverUrl}/.well-known/widehalo`) but was NEVER routed anywhere —
// confirmed via grep across routes/api.php and routes/web.php, this server
// has never once been discoverable by a partner WideHalo/Life MDG
// instance, and `discover()`'d ping against another server running this
// same codebase would also fail (its own identical route was equally
// missing). Deliberately public/unauthenticated, outside the auth+
// module:Integration group above — this is the well-known bootstrap
// discovery endpoint every federation server must expose before any
// connection/shared secret exists, mirroring receiveInvite()'s own
// documented "first contact between two servers" trust model.
Route::get('/.well-known/widehalo', [WhbFederationController::class, 'wellKnown'])
    ->name('integration.federation.well-known');
