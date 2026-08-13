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
*/

Route::middleware(['auth:sanctum', 'throttle:secrets'])->prefix('v1/secrets')->group(function () {
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
