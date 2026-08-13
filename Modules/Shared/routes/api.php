<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1/shared')->group(function () {
    Route::get('countries', 'Modules\Shared\Http\Controllers\Api\CountryController@index');
    Route::get('countries/{code}', 'Modules\Shared\Http\Controllers\Api\CountryController@show');
    Route::get('currencies', 'Modules\Shared\Http\Controllers\Api\CountryController@currencies');
    Route::get('countries/{code}/tax-rates', 'Modules\Shared\Http\Controllers\Api\CountryController@taxRates');
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('v1/shared')->group(function () {
    Route::post('ai/assist', [\Modules\Shared\Http\Controllers\Api\SharedAiAssistController::class, 'assist'])
        ->name('shared.ai.assist');
});
