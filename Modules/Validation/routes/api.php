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
    // Chantier 19 Lot 3: was {workflow} — ApprovalWorkflowController::show()
    // type-hints $approval_workflow, not $workflow (unlike the sibling
    // ApprovalRuleController's methods on the approval-workflows/{workflow}/
    // rules* routes below, which really do use $workflow and are left
    // alone). Same route-parameter-name-mismatch bug as ApprovalRequest's
    // routes above — confirmed empirically that show()/update()/destroy()/
    // cloneWorkflow() on this controller all silently operated on a
    // brand-new, empty ApprovalWorkflow instead of the real one: show()
    // returned blank data, update() would have INSERTed a new empty row
    // instead of updating the real one (Eloquent's update() on a
    // non-existent model saves via INSERT, not UPDATE), and
    // cloneWorkflow() — caught by this chantier's own new regression test
    // — copied zero rules from the real workflow since $workflow->rules
    // resolved against an unsaved, empty instance.
    Route::get('workflows/{approval_workflow}', [ApprovalWorkflowController::class, 'show']);
    Route::get('requests', [ApprovalRequestController::class, 'index']);
    // Chantier 19 Lot 3: this segment was named {request} while
    // ApprovalRequestController::show() type-hints $approval_request — a
    // route-parameter-name mismatch Laravel's implicit route-model-binding
    // resolves silently rather than erroring on (ImplicitRouteBinding::
    // getParameterName() finds no match and simply skips binding that
    // parameter; ResolvesRouteDependencies::transformDependency() then
    // treats it as an ordinary unresolved class dependency and
    // container->make()s a brand-new, empty ApprovalRequest instead of the
    // real one from the URL) — confirmed empirically via a real HTTP
    // request that show() returned 403 even for the request's own real
    // requester, and would have returned blank/empty data instead of the
    // real request for an admin/manager. Renamed to match the controller's
    // real parameter name, the same fix already applied to the sibling
    // approve/reject routes in this exact file.
    Route::get('requests/{approval_request}', [ApprovalRequestController::class, 'show']);
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
    //
    // Chantier 19 Lot 3: was {workflow} — see the identical fix + full
    // rationale on the sibling workflows/{approval_workflow} alias route
    // above.
    Route::get('approval-workflows', [ApprovalWorkflowController::class, 'index']);
    Route::get('approval-workflows/{approval_workflow}', [ApprovalWorkflowController::class, 'show']);

    // Approval Rules (nested under workflows) - reads
    Route::get('approval-workflows/{workflow}/rules', [ApprovalRuleController::class, 'index']);
    Route::get('approval-workflows/{workflow}/rules/{rule}', [ApprovalRuleController::class, 'show']);

    // Approval Requests - reads
    //
    // Chantier 19 Lot 3: both segments below were named {request} while
    // show()/history() type-hint $approval_request — the identical real
    // route-parameter-name-mismatch bug fixed above on the sibling
    // requests/{approval_request} alias route (see that route's comment
    // for the full mechanism). Confirmed empirically via a real HTTP
    // request: the request's own real requester got 403 from show(), and
    // history() 403'd or (for admin/manager) returned an always-empty
    // history — both operating on a brand-new, empty ApprovalRequest
    // instead of the real one from the URL, not the real record at all.
    Route::get('approval-requests', [ApprovalRequestController::class, 'index']);
    Route::get('approval-requests/{approval_request}', [ApprovalRequestController::class, 'show']);
    Route::get('approval-requests/{approval_request}/history', [ApprovalRequestController::class, 'history']);

    // Approval Hierarchies - reads
    //
    // Chantier 19 Lot 3: was {hierarchy} — ApprovalHierarchyController::
    // show() type-hints $approval_hierarchy, the identical route-parameter-
    // name-mismatch bug as ApprovalWorkflow/ApprovalRequest above (see
    // those routes' comments for the full mechanism).
    Route::get('approval-hierarchies', [ApprovalHierarchyController::class, 'index']);
    Route::get('approval-hierarchies/{approval_hierarchy}', [ApprovalHierarchyController::class, 'show']);

    // Structural mutations (workflow/rule/hierarchy configuration) — these
    // routes had NO role restriction at all before this change: any
    // authenticated user could create/edit/delete approval workflows,
    // thresholds, and hierarchies. Gated to admin/super-admin, consistent
    // with the same gate already used on v1/admin/modules* (Setup module).
    Route::middleware(['throttle:create_post', 'role:admin,super-admin'])->group(function () {
        Route::post('approval-workflows', [ApprovalWorkflowController::class, 'store']);
        // Chantier 19 Lot 3: was {workflow} on all 3 of these — see the
        // full rationale on the workflows/{approval_workflow} route above.
        // ApprovalRuleController's own methods 2 lines below on the same
        // approval-workflows/{workflow}/rules* paths really do use
        // $workflow and are deliberately left unchanged.
        Route::put('approval-workflows/{approval_workflow}', [ApprovalWorkflowController::class, 'update']);
        Route::delete('approval-workflows/{approval_workflow}', [ApprovalWorkflowController::class, 'destroy']);
        Route::post('approval-workflows/{approval_workflow}/clone', [ApprovalWorkflowController::class, 'cloneWorkflow']);

        Route::post('approval-workflows/{workflow}/rules', [ApprovalRuleController::class, 'store']);
        Route::put('approval-workflows/{workflow}/rules/{rule}', [ApprovalRuleController::class, 'update']);
        Route::delete('approval-workflows/{workflow}/rules/{rule}', [ApprovalRuleController::class, 'destroy']);

        Route::post('approval-hierarchies', [ApprovalHierarchyController::class, 'store']);
        // Chantier 19 Lot 3: was {hierarchy}/{level} — the identical
        // route-parameter-name-mismatch bug (ApprovalHierarchyController's
        // methods type-hint $approval_hierarchy/$hierarchy_level, not
        // $hierarchy/$level).
        Route::put('approval-hierarchies/{approval_hierarchy}', [ApprovalHierarchyController::class, 'update']);
        Route::delete('approval-hierarchies/{approval_hierarchy}', [ApprovalHierarchyController::class, 'destroy']);
        Route::post('approval-hierarchies/{approval_hierarchy}/levels', [ApprovalHierarchyController::class, 'addLevel']);
        Route::post('approval-hierarchies/{approval_hierarchy}/levels/{hierarchy_level}/approvers', [ApprovalHierarchyController::class, 'addLevelApprovers']);
    });

    // Action mutations (day-to-day approval flow) — not role-gated at the
    // route level: ApprovalRequestPolicy restricts create/approve/reject/
    // delegate to the actual requester/resolved approver per request, which
    // a blanket admin-only gate here would break.
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('approval-requests', [ApprovalRequestController::class, 'store']);
        Route::post('approval-requests/{approval_request}/approve', [ApprovalRequestController::class, 'approve']);
        Route::post('approval-requests/{approval_request}/reject', [ApprovalRequestController::class, 'reject']);
        // Chantier 19 Lot 3: was {request} — the identical route-parameter-
        // name-mismatch bug fixed above (delegate() type-hints
        // $approval_request too), which silently resolved to a brand-new,
        // empty ApprovalRequest instead of the real one from the URL.
        Route::post('approval-requests/{approval_request}/delegate', [ApprovalRequestController::class, 'delegate']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/validation')->group(function () {
    Route::post('ai/assist', [\Modules\Validation\Http\Controllers\Api\ValidationAiAssistController::class, 'assist'])
        ->name('validation.ai.assist');
});
