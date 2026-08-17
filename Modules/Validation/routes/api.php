<?php

use Illuminate\Support\Facades\Route;
use Modules\Validation\Http\Controllers\Api\ApprovalHierarchyController;
use Modules\Validation\Http\Controllers\Api\ApprovalRequestController;
use Modules\Validation\Http\Controllers\Api\ApprovalRuleController;
use Modules\Validation\Http\Controllers\Api\ApprovalWorkflowController;
use Modules\Validation\Http\Controllers\Api\ValidationRuleController;

// Generic data-validation rule engine's "validate this payload" endpoint --
// distinct from the /validation-rules CRUD, which lives at api/v1/validation-rules
// (see Modules/Validation/routes/validation-rules.php).
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])
    ->post('validate', [ValidationRuleController::class, 'validateData']);

// Alias routes (short form) for backwards compatibility with tests
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->group(function () {
    Route::get('workflows', [ApprovalWorkflowController::class, 'index']);
    Route::get('workflows/{workflow}', [ApprovalWorkflowController::class, 'show']);
    Route::get('requests', [ApprovalRequestController::class, 'index']);
    Route::get('requests/{request}', [ApprovalRequestController::class, 'show']);
    Route::middleware(['throttle:create_post', 'role:admin,super-admin'])->group(function () {
        Route::post('workflows', [ApprovalWorkflowController::class, 'store']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        // Not role-gated: ApprovalRequestPolicy already restricts approve/reject
        // to the specific resolved approver for the request's current level —
        // a blanket admin-only gate here would break the actual approval flow.
        Route::post('requests/{approval_request}/approve', [ApprovalRequestController::class, 'approve']);
        Route::post('requests/{approval_request}/reject', [ApprovalRequestController::class, 'reject']);
    });
});

// Default: Simple GET throttle (1000 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->group(function () {
    // Approval Workflows - reads
    Route::get('approval-workflows', [ApprovalWorkflowController::class, 'index']);
    Route::get('approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'show']);

    // Approval Rules (nested under workflows) - reads
    Route::get('approval-workflows/{workflow}/rules', [ApprovalRuleController::class, 'index']);
    Route::get('approval-workflows/{workflow}/rules/{rule}', [ApprovalRuleController::class, 'show']);

    // Approval Requests - reads
    Route::get('approval-requests', [ApprovalRequestController::class, 'index']);
    Route::get('approval-requests/{request}', [ApprovalRequestController::class, 'show']);
    Route::get('approval-requests/{request}/history', [ApprovalRequestController::class, 'history']);

    // Approval Hierarchies - reads
    Route::get('approval-hierarchies', [ApprovalHierarchyController::class, 'index']);
    Route::get('approval-hierarchies/{hierarchy}', [ApprovalHierarchyController::class, 'show']);

    // Structural mutations (workflow/rule/hierarchy configuration) — these
    // routes had NO role restriction at all before this change: any
    // authenticated user could create/edit/delete approval workflows,
    // thresholds, and hierarchies. Gated to admin/super-admin, consistent
    // with the same gate already used on v1/admin/modules* (Setup module).
    Route::middleware(['throttle:create_post', 'role:admin,super-admin'])->group(function () {
        Route::post('approval-workflows', [ApprovalWorkflowController::class, 'store']);
        Route::put('approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'update']);
        Route::delete('approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'destroy']);

        Route::post('approval-workflows/{workflow}/rules', [ApprovalRuleController::class, 'store']);
        Route::put('approval-workflows/{workflow}/rules/{rule}', [ApprovalRuleController::class, 'update']);
        Route::delete('approval-workflows/{workflow}/rules/{rule}', [ApprovalRuleController::class, 'destroy']);

        Route::post('approval-hierarchies', [ApprovalHierarchyController::class, 'store']);
        Route::put('approval-hierarchies/{hierarchy}', [ApprovalHierarchyController::class, 'update']);
        Route::delete('approval-hierarchies/{hierarchy}', [ApprovalHierarchyController::class, 'destroy']);
        Route::post('approval-hierarchies/{hierarchy}/levels', [ApprovalHierarchyController::class, 'addLevel']);
        Route::post('approval-hierarchies/{hierarchy}/levels/{level}/approvers', [ApprovalHierarchyController::class, 'addLevelApprovers']);
    });

    // Action mutations (day-to-day approval flow) — not role-gated at the
    // route level: ApprovalRequestPolicy restricts create/approve/reject/
    // delegate to the actual requester/resolved approver per request, which
    // a blanket admin-only gate here would break.
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('approval-requests', [ApprovalRequestController::class, 'store']);
        Route::post('approval-requests/{approval_request}/approve', [ApprovalRequestController::class, 'approve']);
        Route::post('approval-requests/{approval_request}/reject', [ApprovalRequestController::class, 'reject']);
        Route::post('approval-requests/{request}/delegate', [ApprovalRequestController::class, 'delegate']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/validation')->group(function () {
    Route::post('ai/assist', [\Modules\Validation\Http\Controllers\Api\ValidationAiAssistController::class, 'assist'])
        ->name('validation.ai.assist');
});
