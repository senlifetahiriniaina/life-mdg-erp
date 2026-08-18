<?php

use Illuminate\Support\Facades\Route;
use Modules\Validation\Http\Controllers\Api\ValidationRuleController;
use Modules\Validation\Http\Controllers\Api\ValidationRuleSetController;

// Sibling of api/v1/validation (not nested under it) -- the generic
// data-validation rule engine's own resource, distinct from the approval
// engine's routes registered under Modules/Validation/routes/api.php.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->group(function () {
    Route::get('validation-rules', [ValidationRuleController::class, 'index']);
    Route::get('validation-rule-sets', [ValidationRuleSetController::class, 'index']);
    Route::get('validation-rule-sets/{ruleSet}', [ValidationRuleSetController::class, 'show']);
    // Matches the sibling single-rule `POST validate` (api.php) precedent —
    // validating a payload is a read-only utility action open to any
    // authenticated user, not an admin-only mutation.
    Route::post('validation-rule-sets/{ruleSet}/validate', [ValidationRuleSetController::class, 'validateData']);

    // Chantier 8.5sv: store/update/destroy had no role gate at all — any
    // authenticated user of any tenant could create/edit/delete validation
    // rules, same RBAC-hole pattern already fixed elsewhere in this app.
    // Matches the role:admin,super-admin gate already used for the sibling
    // approval-workflows/approval-hierarchies mutation routes in api.php.
    Route::middleware(['throttle:create_post', 'role:admin,super-admin'])->group(function () {
        Route::post('validation-rules', [ValidationRuleController::class, 'store']);
        Route::put('validation-rules/{validationRule}', [ValidationRuleController::class, 'update']);
        Route::delete('validation-rules/{validationRule}', [ValidationRuleController::class, 'destroy']);
        Route::post('validation-rules/{validationRule}/dependencies', [ValidationRuleController::class, 'addDependency']);

        // Chantier 8.5sv: ValidationEngine::createRuleSet()/validateWithRuleSet()
        // were real, tested (Chantier5) methods with zero controller/route
        // anywhere in the app before this.
        Route::post('validation-rule-sets', [ValidationRuleSetController::class, 'store']);
        Route::post('validation-rule-sets/{ruleSet}/rules', [ValidationRuleSetController::class, 'addRule']);
    });
});
