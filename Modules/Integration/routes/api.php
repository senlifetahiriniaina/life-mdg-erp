<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\Api\BackendStatusController;
use Modules\Integration\Http\Controllers\Api\IntegrationController;
use Modules\Integration\Http\Controllers\Api\WhbFederationController;
use Modules\Integration\Http\Controllers\Api\WhbPartnerController;

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])
    ->prefix('v1/integration')
    ->name('integration.')
    ->group(function () {

        // Connector collection
        Route::get('connectors', [IntegrationController::class, 'index'])
            ->name('connectors.index');

        Route::post('connectors', [IntegrationController::class, 'store'])
            ->name('connectors.store');

        // Connector resource
        Route::get('connectors/{connector}', [IntegrationController::class, 'show'])
            ->name('connectors.show');

        // Actions on a connector
        Route::post('connectors/{connector}/activate', [IntegrationController::class, 'activate'])
            ->name('connectors.activate');

        Route::post('connectors/{connector}/webhook', [IntegrationController::class, 'addWebhook'])
            ->name('connectors.webhook.add');

        Route::post('connectors/{connector}/dispatch', [IntegrationController::class, 'dispatch'])
            ->name('connectors.dispatch');

        Route::get('connectors/{connector}/logs', [IntegrationController::class, 'logs'])
            ->name('connectors.logs');

        // Tenant-level stats
        Route::get('stats', [IntegrationController::class, 'stats'])
            ->name('stats');

        // ── Supabase ──────────────────────────────────────────────────────────
        Route::get('supabase/status', [BackendStatusController::class, 'supabaseStatus'])
            ->name('supabase.status');

        Route::post('supabase/test', [BackendStatusController::class, 'supabaseTest'])
            ->name('supabase.test');

        // ── Firebase ──────────────────────────────────────────────────────────
        Route::get('firebase/status', [BackendStatusController::class, 'firebaseStatus'])
            ->name('firebase.status');

        Route::post('firebase/test-push', [BackendStatusController::class, 'firebaseTestPush'])
            ->name('firebase.test-push');
    });

// ── WideHalo Bridge — Partner management (authenticated) ─────────────────────
// Chantier 8.6: this group had no module:/role: gate at all — any
// authenticated user of any role could approve/reject/suspend federation
// partner connections. Manages inter-company data-sharing, so admin-tier
// only (mirrors the role tier this app already reserves for tenant-wide
// integration/federation management, e.g. Core's superadmin/* group).
Route::prefix('v1/whb')->middleware('auth:sanctum', 'session.security', 'tenancy.user', 'module:Integration', 'role:admin,super-admin')->name('whb.')->group(function () {
    Route::get('connections', [WhbPartnerController::class, 'index'])
        ->name('connections.index');

    Route::post('connections/invite', [WhbPartnerController::class, 'invite'])
        ->name('connections.invite');

    Route::post('connections/join', [WhbPartnerController::class, 'join'])
        ->name('connections.join');

    Route::get('connections/{id}', [WhbPartnerController::class, 'show'])
        ->name('connections.show');

    Route::post('connections/{id}/approve', [WhbPartnerController::class, 'approve'])
        ->name('connections.approve');

    Route::post('connections/{id}/reject', [WhbPartnerController::class, 'reject'])
        ->name('connections.reject');

    Route::post('connections/{id}/suspend', [WhbPartnerController::class, 'suspend'])
        ->name('connections.suspend');

    Route::get('connections/{id}/exchanges', [WhbPartnerController::class, 'exchanges'])
        ->name('connections.exchanges');

    Route::post('send', [WhbPartnerController::class, 'send'])
        ->name('send');

    Route::get('inbox', [WhbPartnerController::class, 'inbox'])
        ->name('inbox.index');

    Route::post('inbox/{exchangeId}/accept', [WhbPartnerController::class, 'acceptIncoming'])
        ->name('inbox.accept');

    Route::post('inbox/{exchangeId}/reject', [WhbPartnerController::class, 'rejectIncoming'])
        ->name('inbox.reject');

    Route::get('discover', [WhbPartnerController::class, 'discover'])
        ->name('discover');
});

// ── WideHalo Bridge — Federation public endpoints (HMAC-signed, no auth) ─────
Route::prefix('v1/federation')
    ->middleware(\App\Http\Middleware\VerifyFederationSignature::class)
    ->name('federation.')
    ->group(function () {
        Route::post('invite', [WhbFederationController::class, 'receiveInvite'])
            ->name('invite');

        Route::post('accept', [WhbFederationController::class, 'receiveAccept'])
            ->name('accept');

        Route::post('exchange', [WhbFederationController::class, 'receiveExchange'])
            ->name('exchange');

        Route::post('refresh', [WhbFederationController::class, 'receiveRefresh'])
            ->name('refresh');
    });

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/integration')->group(function () {
    Route::post('ai/assist', [\Modules\Integration\Http\Controllers\Api\IntegrationAiAssistController::class, 'assist'])
        ->name('integration.ai.assist');
});
