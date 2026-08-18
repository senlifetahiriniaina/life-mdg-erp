<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Api\SettingsController;

/*
 * Chantier 10: this whole file had only auth:sanctum/session.security/
 * tenancy.user — no module:/role: gate at all, unlike every other module in
 * this app (CLAUDE.md flags Settings/Shared explicitly as the two modules
 * confirmed to have neither). SettingsController's own methods already call
 * authorize() against a correctly-written, company_id-scoped SettingPolicy
 * (viewAny() is deliberately permissive by design, update()/bulk()/index()
 * are properly gated) — this route-level gate is defense-in-depth for
 * consistency with the rest of the app, not a fix for a bypassable Policy.
 * 'employee' is included deliberately, matching this app's established
 * broad-read-access-for-every-employee convention (e.g. Inventory/HR route
 * groups) — reading a module's own settings isn't itself sensitive per the
 * policy's own comment.
 */
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Settings', 'role:employee,manager,admin,super-admin'])->group(function () {
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
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Settings', 'role:employee,manager,admin,super-admin'])->group(function () {
    Route::post('ai/assist', [\Modules\Settings\Http\Controllers\Api\SettingsAiAssistController::class, 'assist'])
        ->name('settings.ai.assist');
});
