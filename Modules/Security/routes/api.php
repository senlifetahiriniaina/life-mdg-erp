<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\ComplianceController;
use Modules\Security\Http\Controllers\EncryptionController;
use Modules\Security\Http\Controllers\IncidentController;
use Modules\Security\Http\Controllers\ThreatIndicatorController;
use Modules\Security\Http\Controllers\AuthenticationEventController;
use Modules\Security\Http\Controllers\ComplianceControlController;
use Modules\Security\Http\Controllers\TrustZoneController;
use Modules\Security\Http\Controllers\ServiceIdentityController;
use Modules\Security\Http\Controllers\RateLimitController;

Route::middleware(['auth:sanctum'])->prefix('v1/security')->group(function () {
    // Compliance Controls
    Route::get('compliance/controls', [ComplianceController::class, 'indexControls']);
    Route::post('compliance/controls', [ComplianceController::class, 'storeControl']);
    Route::get('compliance/controls/{control}', [ComplianceController::class, 'showControl']);
    Route::put('compliance/controls/{control}', [ComplianceController::class, 'updateControl']);
    Route::post('compliance/controls/{control}/verify', [ComplianceController::class, 'verifyControl']);
    Route::delete('compliance/controls/{control}', [ComplianceController::class, 'deleteControl']);

    // Compliance Audits
    Route::get('compliance/audits', [ComplianceController::class, 'indexAudits']);
    Route::post('compliance/audits', [ComplianceController::class, 'storeAudit']);
    Route::get('compliance/audits/{audit}', [ComplianceController::class, 'showAudit']);
    Route::put('compliance/audits/{audit}', [ComplianceController::class, 'updateAudit']);
    Route::post('compliance/audits/{audit}/complete', [ComplianceController::class, 'completeAudit']);
    Route::delete('compliance/audits/{audit}', [ComplianceController::class, 'deleteAudit']);

    // Compliance Violations
    Route::get('compliance/violations', [ComplianceController::class, 'indexViolations']);
    Route::get('compliance/violations/{violation}', [ComplianceController::class, 'showViolation']);
    Route::put('compliance/violations/{violation}', [ComplianceController::class, 'updateViolation']);

    // Encryption Keys
    Route::get('encryption/keys', [EncryptionController::class, 'indexKeys']);
    Route::post('encryption/keys', [EncryptionController::class, 'storeKey']);
    Route::get('encryption/keys/{key}', [EncryptionController::class, 'showKey']);
    Route::put('encryption/keys/{key}', [EncryptionController::class, 'updateKey']);
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
    Route::put('incidents/{incident}', [IncidentController::class, 'updateIncident']);
    Route::post('incidents/{incident}/investigate', [IncidentController::class, 'investigateIncident']);
    Route::post('incidents/{incident}/resolve', [IncidentController::class, 'resolveIncident']);
    Route::delete('incidents/{incident}', [IncidentController::class, 'deleteIncident']);
    Route::get('incidents/{incident}/responses', [IncidentController::class, 'indexResponses']);
    Route::post('incidents/{incident}/responses', [IncidentController::class, 'storeResponse']);

    // Threat Indicators
    Route::get('threats', [IncidentController::class, 'indexThreats']);
    Route::post('threats', [IncidentController::class, 'storeThreat']);
    Route::post('threats/{threat}/whitelist', [IncidentController::class, 'whitelistThreat']);
    Route::post('threats/{threat}/unwhitelist', [IncidentController::class, 'unwhitelistThreat']);

    // ─── New: Threat Indicators (full CRUD) ───────────────────────────────────
    Route::prefix('threat-indicators')->group(function () {
        Route::get('/',                         [ThreatIndicatorController::class, 'index']);
        Route::post('/',                        [ThreatIndicatorController::class, 'store']);
        Route::get('/severity-summary',         [ThreatIndicatorController::class, 'severitySummary']);
        Route::get('/{threat}',                 [ThreatIndicatorController::class, 'show']);
        Route::put('/{threat}',                 [ThreatIndicatorController::class, 'update']);
        Route::delete('/{threat}',              [ThreatIndicatorController::class, 'destroy']);
    });

    // ─── Authentication Events ────────────────────────────────────────────────
    Route::prefix('auth-events')->group(function () {
        Route::get('/',                   [AuthenticationEventController::class, 'index']);
        Route::get('/summary',            [AuthenticationEventController::class, 'summary']);
        Route::get('/suspicious',         [AuthenticationEventController::class, 'suspiciousActivity']);
        Route::get('/{authenticationEvent}', [AuthenticationEventController::class, 'show']);
    });

    // ─── Compliance Controls (dedicated controller) ───────────────────────────
    Route::prefix('compliance-controls')->group(function () {
        Route::get('/',                            [ComplianceControlController::class, 'index']);
        Route::post('/',                           [ComplianceControlController::class, 'store']);
        Route::get('/framework-summary',           [ComplianceControlController::class, 'frameworkSummary']);
        Route::get('/{complianceControl}',         [ComplianceControlController::class, 'show']);
        Route::put('/{complianceControl}',         [ComplianceControlController::class, 'update']);
        Route::delete('/{complianceControl}',      [ComplianceControlController::class, 'destroy']);
        Route::post('/{complianceControl}/verify', [ComplianceControlController::class, 'verify']);
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
    Route::prefix('rate-limits')->group(function () {
        Route::get('/status',      [RateLimitController::class, 'status']);
        Route::post('/reset',      [RateLimitController::class, 'reset']);
        Route::get('/blocked-ips', [RateLimitController::class, 'blockedIps']);
        Route::post('/block-ip',   [RateLimitController::class, 'blockIp']);
        Route::post('/unblock-ip', [RateLimitController::class, 'unblockIp']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('v1/security')->group(function () {
    Route::post('ai/assist', [\Modules\Security\Http\Controllers\Api\SecurityAiAssistController::class, 'assist'])
        ->name('security.ai.assist');
});
