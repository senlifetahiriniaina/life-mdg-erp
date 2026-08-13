<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Api\SettingsController;

Route::middleware('auth:sanctum')->group(function () {
    // GET all settings (admin only)
    Route::get('/', [SettingsController::class, 'index']);

    // GET all settings for a module
    Route::get('/{module}', [SettingsController::class, 'showModule']);

    // Bulk update settings for a module
    Route::post('/{module}/bulk', [SettingsController::class, 'bulk']);

    // Update a single setting
    Route::put('/{module}/{key}', [SettingsController::class, 'update']);
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('v1/settings')->group(function () {
    Route::post('ai/assist', [\Modules\Settings\Http\Controllers\Api\SettingsAiAssistController::class, 'assist'])
        ->name('settings.ai.assist');
});
