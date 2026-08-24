<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\ComplianceController;
use Modules\Security\Http\Controllers\EncryptionController;
use Modules\Security\Http\Controllers\IncidentController;
use Modules\Security\Http\Controllers\SecurityDashboardController;
use Modules\Security\Http\Controllers\ThreatIndicatorController;
use Modules\Security\Http\Controllers\AuthenticationEventController;
use Modules\Security\Http\Controllers\TrustZoneController;
use Modules\Security\Http\Controllers\ServiceIdentityController;
use Modules\Security\Http\Controllers\RateLimitController;

// Chantier 32.3 (14-layer deep audit): this whole module's route group never
// had a module:/role: gate at all — only 2 of its 8 sub-resources
// (auth-events, rate-limits) were ever restricted to
// role:security-admin,admin,super-admin, and even those permission strings
// were (until the same chantier's RolesAndPermissionsSeeder fix) silently
// also handed to every plain manager/employee via the generic MODULES loop.
// Added here as a route-level gate too (defense-in-depth alongside the
// seeder fix) — every real caller in this app (Index.vue's dashboard,
// existing tests) already only ever uses security-admin, confirmed via grep
// before this change.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Security', 'role:security-admin,admin,super-admin'])
    ->prefix('v1/security')
    ->group(function () {
    // Compliance Controls
    Route::get('compliance/controls', [ComplianceController::class, 'indexControls']);
    Route::post('compliance/controls', [ComplianceController::class, 'storeControl']);
    Route::get('compliance/controls/{control}', [ComplianceController::class, 'showControl']);
    Route::match(['put', 'patch'], 'compliance/controls/{control}', [ComplianceController::class, 'updateControl']);
    Route::post('compliance/controls/{control}/verify', [ComplianceController::class, 'verifyControl']);
    Route::delete('compliance/controls/{control}', [ComplianceController::class, 'deleteControl']);

    // Compliance Audits
    Route::get('compliance/audits', [ComplianceController::class, 'indexAudits']);
    Route::post('compliance/audits', [ComplianceController::class, 'storeAudit']);
    Route::get('compliance/audits/{audit}', [ComplianceController::class, 'showAudit']);
    Route::match(['put', 'patch'], 'compliance/audits/{audit}', [ComplianceController::class, 'updateAudit']);
    Route::post('compliance/audits/{audit}/complete', [ComplianceController::class, 'completeAudit']);
    Route::delete('compliance/audits/{audit}', [ComplianceController::class, 'deleteAudit']);

    // Compliance Violations
    Route::get('compliance/violations', [ComplianceController::class, 'indexViolations']);
    Route::get('compliance/violations/{violation}', [ComplianceController::class, 'showViolation']);
    Route::match(['put', 'patch'], 'compliance/violations/{violation}', [ComplianceController::class, 'updateViolation']);

    // Encryption Keys
    Route::get('encryption/keys', [EncryptionController::class, 'indexKeys']);
    Route::post('encryption/keys', [EncryptionController::class, 'storeKey']);
    Route::get('encryption/keys/{key}', [EncryptionController::class, 'showKey']);
    Route::match(['put', 'patch'], 'encryption/keys/{key}', [EncryptionController::class, 'updateKey']);
    Route::post('encryption/keys/{key}/rotate', [EncryptionController::class, 'rotateKey']);
    Route::post('encryption/keys/{key}/revoke', [EncryptionController::class, 'revokeKey']);
    Route::delete('encryption/keys/{key}', [EncryptionController::class, 'deleteKey']);
    Route::get('encryption/rotation-logs', [EncryptionController::class, 'indexRotationLogs']);
    Route::get('encryption/encrypted-fields', [EncryptionController::class, 'indexEncryptedFields']);
    Route::post('encryption/encrypted-fields', [EncryptionController::class, 'storeEncryptedField']);

    // Security Incidents
    Route::get('incidents', [IncidentController::class, 'indexIncidents']);
    Route::post('incidents', [IncidentController::class, 'storeIncident']);
    Route::get('incidents/{incident}', [IncidentController::class, 'showIncident']);
    Route::match(['put', 'patch'], 'incidents/{incident}', [IncidentController::class, 'updateIncident']);
    Route::post('incidents/{incident}/investigate', [IncidentController::class, 'investigateIncident']);
    Route::post('incidents/{incident}/resolve', [IncidentController::class, 'resolveIncident']);
    Route::delete('incidents/{incident}', [IncidentController::class, 'deleteIncident']);
    Route::get('incidents/{incident}/responses', [IncidentController::class, 'indexResponses']);
    Route::post('incidents/{incident}/responses', [IncidentController::class, 'storeResponse']);

    // Chantier 32.3: dashboard aggregate — replaces Index.vue's client-side
    // recomputation of the same numbers from 3 separate list calls, and
    // fixes SecurityAuditService::getSecuritySummary()'s hardcoded
    // 'critical_threats' => 0 stub along the way (see the service itself).
    Route::get('summary', [SecurityDashboardController::class, 'summary']);

    // ─── Threat Indicators (full CRUD + whitelist toggle) ─────────────────────
    // Chantier 32.3: the legacy IncidentController::indexThreats/storeThreat
    // routes that used to live here (GET/POST threats) were a confirmed-dead
    // duplicate of this same controller's index()/store() — zero real caller
    // anywhere outside their own test file, a different indicator_type enum
    // than this controller validates, and Index.vue already only ever calls
    // this one. Deleted; whitelist/unwhitelist (a real, distinct capability
    // the legacy routes had that this controller didn't) were ported over
    // instead of being lost.
    Route::prefix('threat-indicators')->group(function () {
        Route::get('/',                         [ThreatIndicatorController::class, 'index']);
        Route::post('/',                        [ThreatIndicatorController::class, 'store']);
        Route::get('/severity-summary',         [ThreatIndicatorController::class, 'severitySummary']);
        Route::get('/{threat}',                 [ThreatIndicatorController::class, 'show']);
        Route::put('/{threat}',                 [ThreatIndicatorController::class, 'update']);
        Route::post('/{threat}/whitelist',      [ThreatIndicatorController::class, 'whitelist']);
        Route::post('/{threat}/unwhitelist',    [ThreatIndicatorController::class, 'unwhitelist']);
        Route::delete('/{threat}',              [ThreatIndicatorController::class, 'destroy']);
    });

    // ─── Authentication Events ────────────────────────────────────────────────
    // AuthenticationEvent has no company_id column at all (cross-tenant by
    // design — it's a security audit trail). Its own role: middleware is
    // now redundant with the outer group's (added in the same chantier) but
    // left in place as explicit, self-documenting defense-in-depth.
    Route::prefix('auth-events')->middleware('role:security-admin,admin,super-admin')->group(function () {
        Route::get('/',                   [AuthenticationEventController::class, 'index']);
        Route::get('/summary',            [AuthenticationEventController::class, 'summary']);
        Route::get('/suspicious',         [AuthenticationEventController::class, 'suspiciousActivity']);
        Route::get('/{authenticationEvent}', [AuthenticationEventController::class, 'show']);
    });

    // ─── Trust Zones ─────────────────────────────────────────────────────────
    Route::prefix('trust-zones')->group(function () {
        Route::get('/',                              [TrustZoneController::class, 'index']);
        Route::post('/',                             [TrustZoneController::class, 'store']);
        Route::get('/{trustZone}',                   [TrustZoneController::class, 'show']);
        Route::put('/{trustZone}',                   [TrustZoneController::class, 'update']);
        Route::delete('/{trustZone}',                [TrustZoneController::class, 'destroy']);
        Route::post('/{trustZone}/assign-resource',  [TrustZoneController::class, 'assignResource']);
    });

    // ─── Service Identities ───────────────────────────────────────────────────
    Route::prefix('service-identities')->group(function () {
        Route::get('/',                              [ServiceIdentityController::class, 'index']);
        Route::post('/',                             [ServiceIdentityController::class, 'store']);
        Route::get('/{serviceIdentity}',             [ServiceIdentityController::class, 'show']);
        Route::put('/{serviceIdentity}',             [ServiceIdentityController::class, 'update']);
        Route::delete('/{serviceIdentity}',          [ServiceIdentityController::class, 'destroy']);
        Route::post('/{serviceIdentity}/rotate',     [ServiceIdentityController::class, 'rotateCredentials']);
        Route::post('/{serviceIdentity}/revoke',     [ServiceIdentityController::class, 'revoke']);
    });

    // ─── Rate Limits ──────────────────────────────────────────────────────────
    // No Eloquent model backs rate-limit state (it lives in Cache), so there's
    // nothing for a Policy to attach to — role: middleware here is now
    // redundant with the outer group's but left in place as explicit,
    // self-documenting defense-in-depth, same pattern as auth-events above.
    Route::prefix('rate-limits')->middleware('role:security-admin,admin,super-admin')->group(function () {
        Route::get('/status',      [RateLimitController::class, 'status']);
        Route::post('/reset',      [RateLimitController::class, 'reset']);
        Route::get('/blocked-ips', [RateLimitController::class, 'blockedIps']);
        Route::post('/block-ip',   [RateLimitController::class, 'blockIp']);
        Route::post('/unblock-ip', [RateLimitController::class, 'unblockIp']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
// Deliberately left outside the security-admin-only gate above, matching the
// established convention elsewhere in this app that an ai/assist endpoint is
// gated by module access, not by the module's own operational role — the
// panel is meant to guide whoever is looking at the page, not just admins.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Security'])->prefix('v1/security')->group(function () {
    Route::post('ai/assist', [\Modules\Security\Http\Controllers\Api\SecurityAiAssistController::class, 'assist'])
        ->name('security.ai.assist');
});
