<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1/shared')->group(function () {
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
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/shared')->group(function () {
    Route::post('ai/assist', [\Modules\Shared\Http\Controllers\Api\SharedAiAssistController::class, 'assist'])
        ->name('shared.ai.assist');
});
