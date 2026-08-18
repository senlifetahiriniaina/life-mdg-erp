<?php

use Illuminate\Support\Facades\Route;

/*
 * Chantier 10: this whole group had NO middleware at all — not even
 * auth:sanctum (CLAUDE.md flags Shared explicitly, alongside Settings, as
 * the two modules confirmed to have neither module:/role: gating). Confirmed
 * via a repo-wide grep that no frontend page or public/unauthenticated
 * onboarding flow calls these endpoints at all today, so gating them behind
 * standard auth breaks nothing live. CountryController/CurrencyController
 * have no authorize() calls of their own (no natural per-record model to
 * gate — this is global reference data, not tenant data), so the route
 * gate is the only real check here, matching the precedent already used
 * for Security's RateLimitController/AuthenticationEventController (no
 * natural model to hang a Policy off).
 */
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Shared', 'role:employee,manager,admin,super-admin'])->prefix('v1/shared')->group(function () {
    Route::get('countries', 'Modules\Shared\Http\Controllers\Api\CountryController@index');
    Route::get('countries/{code}', 'Modules\Shared\Http\Controllers\Api\CountryController@show');
    Route::get('countries/{code}/tax-rates', 'Modules\Shared\Http\Controllers\Api\CountryController@taxRates');

    // Real, Currency-model-backed endpoints (filtering by region/CFA/active,
    // single-currency lookup, and genuine conversion — CountryController::currencies()
    // was only ever a bare unfiltered DB::table('shared_currencies') list, so
    // CurrencyController::index() replaces it here as the real superset rather
    // than being registered as a second, silently-losing 'currencies' route
    // (the same first-vs-last route-registration landmine already documented
    // and fixed for Helpdesk's KB routes elsewhere in this app).
    Route::get('currencies', 'Modules\Shared\Http\Controllers\Api\CurrencyController@index');
    Route::get('currencies/{code}', 'Modules\Shared\Http\Controllers\Api\CurrencyController@show');
    Route::post('currencies/convert', 'Modules\Shared\Http\Controllers\Api\CurrencyController@convert');
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Shared', 'role:employee,manager,admin,super-admin'])->prefix('v1/shared')->group(function () {
    Route::post('ai/assist', [\Modules\Shared\Http\Controllers\Api\SharedAiAssistController::class, 'assist'])
        ->name('shared.ai.assist');
});
