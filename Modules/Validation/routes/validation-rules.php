<?php

use Illuminate\Support\Facades\Route;
use Modules\Validation\Http\Controllers\Api\ValidationRuleController;

// Sibling of api/v1/validation (not nested under it) -- the generic
// data-validation rule engine's own resource, distinct from the approval
// engine's routes registered under Modules/Validation/routes/api.php.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->group(function () {
    Route::get('validation-rules', [ValidationRuleController::class, 'index']);
    Route::post('validation-rules', [ValidationRuleController::class, 'store']);
    Route::put('validation-rules/{validationRule}', [ValidationRuleController::class, 'update']);
    Route::delete('validation-rules/{validationRule}', [ValidationRuleController::class, 'destroy']);
});
