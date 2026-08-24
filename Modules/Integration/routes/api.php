<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\Api\BackendStatusController;
use Modules\Integration\Http\Controllers\Api\ExternalIntegrationController;
use Modules\Integration\Http\Controllers\Api\IntegrationController;
use Modules\Integration\Http\Controllers\Api\WhbFederationController;
use Modules\Integration\Http\Controllers\Api\WhbPartnerController;

// Chantier 32.6: this whole group had no module: gate at all — added for
// consistency with the module:+role: convention used across every other
// module in this app (module: is Modules\Core\Http\Middleware\
// CheckModuleEnabled, a per-tenant module ON/OFF business toggle — e.g. a
// tenant that hasn't purchased/enabled Integration — NOT a permission
// check; on a fresh install with no tenant_modules rows at all it is a
// no-op by design, "on by default"). The real per-connector authorize()
// calls (fixed in Chantier 8.5-light/19) already deny a user with zero
// integration.* permissions on every connector-CRUD/external-integration
// route via their own Policy. BackendStatusController's Supabase/Firebase
// status+test endpoints are the one real gap in this group: NO authorize()
// call of any kind (no natural per-record model to hang a Policy off, same
// category as Security's RateLimitController/AuthenticationEventController)
// — any authenticated user of ANY role, including one with zero
// integration.* permissions, could trigger a real outbound Supabase probe
// or send a real test FCM push notification — confirmed empirically that
// module:Integration alone does NOT close this (it's not an RBAC check),
// closed instead with a real role: gate scoped to just those 4 routes,
// matching the broad "employee+" tier that already implicitly has
// integration.* permissions via the generic role-seeding loop.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Integration'])
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

        // Chantier 32.6: IntegrationConnectorPolicy::delete() has existed
        // and been tested since Chantier 8.6, but there was never a
        // destroy() method/route — IntegrationsIndex.vue's
        // disconnectConnector() has always called this exact URL and
        // always gotten a 404, confirmed empirically.
        Route::delete('connectors/{connector}', [IntegrationController::class, 'destroy'])
            ->name('connectors.destroy');

        // Tenant-level stats
        Route::get('stats', [IntegrationController::class, 'stats'])
            ->name('stats');

        // ── Supabase / Firebase — role: gate, see comment above ────────────────
        Route::middleware('role:employee,manager,finance-manager,system-admin,tenant-admin,admin,super-admin')->group(function () {
            Route::get('supabase/status', [BackendStatusController::class, 'supabaseStatus'])
                ->name('supabase.status');

            Route::post('supabase/test', [BackendStatusController::class, 'supabaseTest'])
                ->name('supabase.test');

            Route::get('firebase/status', [BackendStatusController::class, 'firebaseStatus'])
                ->name('firebase.status');

            Route::post('firebase/test-push', [BackendStatusController::class, 'firebaseTestPush'])
                ->name('firebase.test-push');
        });

        // ── External integrations (mobile money / e-commerce / business tools) ──
        // Chantier 32.6: activates Modules\Integration\Services\
        // IntegrationManager — Orange Money/Wave/MTN MoMo/M-Pesa/Shopify/
        // WooCommerce/Jumia/Google Workspace/Zapier — a real, fully-written,
        // CLAUDE.md-documented Africa First subsystem that had zero
        // controller/route anywhere before this chantier.
        Route::get('external', [ExternalIntegrationController::class, 'index'])
            ->name('external.index');

        Route::post('external/{key}/connect', [ExternalIntegrationController::class, 'connect'])
            ->name('external.connect');

        Route::post('external/{key}/disconnect', [ExternalIntegrationController::class, 'disconnect'])
            ->name('external.disconnect');

        Route::post('external/{key}/test', [ExternalIntegrationController::class, 'test'])
            ->name('external.test');

        Route::post('external/{key}/sync', [ExternalIntegrationController::class, 'sync'])
            ->name('external.sync');
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
