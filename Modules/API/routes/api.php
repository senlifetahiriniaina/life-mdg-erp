<?php

use Illuminate\Support\Facades\Route;

// Chantier 10: no module:/role: gate at all — ApiKeyPolicy (registered +
// called via authorize() since Chantier 8.5-light) already restricts
// create/update/delete to admin-tier roles, but index/show/logs/stats had
// no role floor at all beyond plain authentication. Added the standard
// module:/role: gate for consistency with the rest of this app.
//
// Chantier 32.5: `webhooks*` was removed from this group entirely — the
// whole ApiWebhook/WebhookController/WebhookPolicy subtree was deleted as a
// confirmed dead/insecure duplicate of the real, live webhook system this
// app already has at App\Models\Webhook / /api/v1/webhooks (see the
// api_webhooks drop migration's docblock). `keys*`'s two real mutating
// actions (store/revoke) also gained the same `throttle:create_post`
// limiter every other module already applies to its own sensitive
// mutating endpoints (Achats/BI/CRM/Accounting) — API key generation had
// no stricter limit than any GET before this, beyond the blanket global
// 60/min applied to every /api/* route in bootstrap/app.php.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:API', 'role:employee,admin,super-admin'])->prefix('v1/api')->group(function () {
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('keys', 'Modules\API\Http\Controllers\Api\ApiKeyController@store');
        Route::delete('keys/{id}/revoke', 'Modules\API\Http\Controllers\Api\ApiKeyController@revoke');
    });

    Route::get('keys', 'Modules\API\Http\Controllers\Api\ApiKeyController@index');
    Route::get('keys/{id}', 'Modules\API\Http\Controllers\Api\ApiKeyController@show');
    Route::get('keys/{id}/logs', 'Modules\API\Http\Controllers\Api\ApiKeyController@logs');

    Route::get('logs', 'Modules\API\Http\Controllers\Api\RequestLogController@index');
    Route::get('logs/stats', 'Modules\API\Http\Controllers\Api\RequestLogController@stats');
});

// Chantier 32.5: the one real, self-contained consumer of the previously
// dead-on-arrival api_keys authentication pipeline — see
// Modules\API\Http\Middleware\AuthenticateApiKey's own docblock for the
// full rationale. Deliberately NOT gated by auth:sanctum/module:API/role: —
// this route is for EXTERNAL callers presenting a raw API key via the
// X-Api-Key header, not a logged-in dashboard session (which is what every
// other route in this file assumes).
Route::middleware('api-key')->prefix('v1/api')->group(function () {
    Route::get('ping', 'Modules\API\Http\Controllers\Api\ApiKeyController@ping');
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/api')->group(function () {
    Route::post('ai/assist', [\Modules\API\Http\Controllers\Api\APIAiAssistController::class, 'assist'])
        ->name('api.ai.assist');
});
