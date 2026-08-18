<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\SecretsController;

/*
|--------------------------------------------------------------------------
| Secrets Management Routes
|--------------------------------------------------------------------------
|
| Centralized secrets management API endpoints with encryption,
| versioning, rotation, and comprehensive access control.
|
| Chantier 10: this group had only auth:sanctum+throttle:secrets — no
| module:/role: gate at all, and SecretsController itself never called
| SecretAccessControl::canAccessSecret() (a real, correctly-written access-
| control service that existed but was never wired into the live controller)
| — any authenticated user of any role, of any tenant (see the
| getTenantId() fixes in SecretsService/SecretAccessControl/
| SecretRotationManager for the tenant-scoping half of this), could list/
| create/rotate/revoke every secret in this AES-256-CBC vault and grant/
| revoke other users' access to it. Added the standard role: gate as the
| first line of defense (secrets administration is an admin-tier action in
| every other sensitive module this session — ServiceIdentityController/
| TrustZoneController/RateLimitController all landed on the same
| role:security-admin,admin,super-admin tier); SecretsController itself now
| also calls the pre-existing canAccessSecret()/checkRoleBasedAccess() for
| per-secret grant-based access (see that controller for the second half).
*/

Route::middleware(['auth:sanctum', 'throttle:secrets', 'module:Core', 'role:security-admin,admin,super-admin'])->prefix('v1/secrets')->group(function () {
    // List secrets
    Route::get('/', [SecretsController::class, 'index']);

    // Get upcoming rotations
    Route::get('rotations/upcoming', [SecretsController::class, 'upcomingRotations']);

    // Create secret
    Route::post('/', [SecretsController::class, 'store']);

    // Get secret metadata (without value)
    Route::get('{name}/metadata', [SecretsController::class, 'metadata']);

    // Get secret value
    Route::get('{name}', [SecretsController::class, 'show']);

    // Rotate secret
    Route::put('{name}/rotate', [SecretsController::class, 'rotate']);

    // Revoke secret
    Route::delete('{name}', [SecretsController::class, 'destroy']);

    // Access control
    Route::get('{name}/access', [SecretsController::class, 'getAccessors']);
    Route::post('{name}/access/grant', [SecretsController::class, 'grantAccess']);
    Route::delete('{name}/access/{userId}', [SecretsController::class, 'revokeAccess']);
});
