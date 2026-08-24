<?php

use Illuminate\Support\Facades\Route;
use Modules\Validation\Http\Controllers\Web\ApprovalRequestController;
use Modules\Validation\Http\Controllers\Web\ValidationRuleWebController;
use Modules\Validation\Http\Controllers\Web\WorkflowWebController;

// Chantier 32.7: added module:Validation (was `auth`-only, no module gate
// at all) — matching the standard pattern used across every other module's
// web routes in this app. Deliberately NOT adding a `role:` restriction on
// top of it: these 5 pages are self-fetch shells whose actual data comes
// from the already RBAC-gated API (or, for show(), an explicit authorize()
// call already added in Chantier 19 Lot 3) — picking a role allowlist here
// risks locking out a legitimate 'approver'-role user from the dashboard
// shell for no real security gain, since index() renders no server data.
Route::middleware(['auth', 'module:Validation'])->group(function () {
    // Approval request dashboard and pages will be served by Inertia
    Route::get('/approval-requests', [ApprovalRequestController::class, 'index'])->name('validation.requests.index');
    Route::get('/approval-requests/{approval_request}', [ApprovalRequestController::class, 'show'])->name('validation.requests.show');

    Route::get('/workflows', [WorkflowWebController::class, 'index'])->name('validation.workflows.index');
    Route::get('/workflows/builder', [WorkflowWebController::class, 'create'])->name('validation.workflows.create');
    Route::get('/workflows/{workflow}/builder', [WorkflowWebController::class, 'builder'])->name('validation.workflows.builder');

    Route::get('/validation-rules', [ValidationRuleWebController::class, 'index'])->name('validation.rules.index');
});
