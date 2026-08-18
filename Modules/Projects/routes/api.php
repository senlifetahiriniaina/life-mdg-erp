<?php

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\Api\AutomationController;
use Modules\Projects\Http\Controllers\Api\EpicController;
use Modules\Projects\Http\Controllers\Api\GanttController;
use Modules\Projects\Http\Controllers\Api\MilestoneController;
use Modules\Projects\Http\Controllers\Api\ProjectController;
use Modules\Projects\Http\Controllers\Api\ProjectMemberController;
use Modules\Projects\Http\Controllers\Api\ProjectReportController;
use Modules\Projects\Http\Controllers\Api\ProjectsAIController;
use Modules\Projects\Http\Controllers\Api\ProjectTeamController;
use Modules\Projects\Http\Controllers\Api\ProjectViewsController;
use Modules\Projects\Http\Controllers\Api\ResourceCapacityController;
use Modules\Projects\Http\Controllers\Api\SprintController;
use Modules\Projects\Http\Controllers\Api\TaskController;
use Modules\Projects\Http\Controllers\Api\TimeEntryController;
use Modules\Projects\Http\Controllers\Api\TimeTrackingController;

// Default: Simple GET throttle (1000 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Projects', 'role:employee,manager,admin', 'throttle:simple_get'])->prefix('v1')->group(function () {
    // Time Tracking & Billing — MUST be registered BEFORE apiResource('projects') to avoid
    // the static segment "time-entries" being captured by the {project} route parameter.
    // IMPORTANT: /start must come BEFORE {timeEntry} parameterized routes.
    Route::get('projects/time-entries', [TimeTrackingController::class, 'index'])->name('projects.tracking.index');
    Route::get('projects/time-entries/{timeEntry}', [TimeTrackingController::class, 'show'])->name('projects.tracking.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/time-entries/start', [TimeTrackingController::class, 'start'])->name('projects.tracking.start');
        Route::post('projects/time-entries', [TimeTrackingController::class, 'store'])->name('projects.tracking.store');
        Route::put('projects/time-entries/{timeEntry}', [TimeTrackingController::class, 'update'])->name('projects.tracking.update');
        Route::delete('projects/time-entries/{timeEntry}', [TimeTrackingController::class, 'destroy'])->name('projects.tracking.destroy');
        Route::post('projects/time-entries/{timeEntry}/stop', [TimeTrackingController::class, 'stop'])->name('projects.tracking.stop');
        Route::post('projects/time-entries/{timeEntry}/bill', [TimeTrackingController::class, 'bill'])->name('projects.tracking.bill');
    });

    // Cross-project time report — MUST be before apiResource('projects') to avoid {project} capture
    Route::get('projects/time-report-global', [TimeTrackingController::class, 'globalReport'])->name('projects.tracking.global-report');

    // Epics global list (Roadmap) — MUST be before apiResource('projects') to avoid {project} capture
    Route::get('projects/epics', [EpicController::class, 'all'])->name('projects.epics.all');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    });

    // Tasks (scoped to projects)
    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks.index');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('projects.tasks.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
        Route::put('tasks/{task}', [TaskController::class, 'update'])->name('projects.tasks.update');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('projects.tasks.destroy');
    });

    // Project-scoped time entries & billing
    Route::get('projects/{project}/time-entries', [TimeTrackingController::class, 'projectTimeEntries'])->name('projects.tracking.project-entries');
    Route::get('projects/{project}/billing', [TimeTrackingController::class, 'projectBilling'])->name('projects.tracking.billing');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/billing/setup', [TimeTrackingController::class, 'setupBilling'])->name('projects.tracking.billing-setup');
        Route::post('projects/{project}/billing/mark-billed', [TimeTrackingController::class, 'markBilled'])->name('projects.tracking.mark-billed');
    });

    // Time entries (per task)
    Route::get('projects/{project}/tasks/{task}/time-entries', [TimeEntryController::class, 'index'])->name('projects.time-entries.index');
    Route::get('projects/{project}/tasks/{task}/time-entries/{timeEntry}', [TimeEntryController::class, 'show'])->name('projects.time-entries.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/tasks/{task}/time-entries', [TimeEntryController::class, 'store'])->name('projects.time-entries.store');
        Route::put('projects/{project}/tasks/{task}/time-entries/{timeEntry}', [TimeEntryController::class, 'update'])->name('projects.time-entries.update');
        Route::delete('projects/{project}/tasks/{task}/time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('projects.time-entries.destroy');
    });

    // Milestones
    Route::get('projects/{project}/milestones', [MilestoneController::class, 'index'])->name('projects.milestones.index');
    Route::get('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'show'])->name('projects.milestones.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/milestones', [MilestoneController::class, 'store'])->name('projects.milestones.store');
        Route::put('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'update'])->name('projects.milestones.update');
        Route::delete('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('projects.milestones.destroy');
    });

    // Project members
    Route::get('projects/{project}/members', [ProjectMemberController::class, 'index'])->name('projects.members.index');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
        Route::put('projects/{project}/members/{user}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
        Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
    });

    // Views
    Route::get('projects/{project}/views/kanban', [ProjectViewsController::class, 'kanban'])->name('projects.views.kanban');
    Route::get('projects/{project}/views/calendar', [ProjectViewsController::class, 'calendar'])->name('projects.views.calendar');
    Route::get('projects/{project}/views/gantt', [ProjectViewsController::class, 'gantt'])->name('projects.views.gantt');

    // Gantt advanced (dependencies + critical path)
    Route::get('projects/{project}/gantt', [GanttController::class, 'show'])->name('projects.gantt.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/tasks/{task}/dependencies', [GanttController::class, 'addDependency'])->name('projects.gantt.dependencies.store');
        Route::delete('projects/tasks/{task}/dependencies/{dependency}', [GanttController::class, 'removeDependency'])->name('projects.gantt.dependencies.destroy');
        Route::patch('projects/tasks/{task}/gantt', [GanttController::class, 'updateDates'])->name('projects.gantt.update-dates');
    });

    // Reports
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('projects/{project}/report', [ProjectReportController::class, 'show'])->name('projects.report.show');
        Route::get('projects/{project}/report/pdf', [ProjectReportController::class, 'pdf'])->name('projects.report.pdf');
        // Chantier 8.4: real, working (Inertia-rendered HTML report), zero route.
        Route::get('projects/{project}/report/html', [ProjectReportController::class, 'html'])->name('projects.report.html');
    });

    // Team & time logs
    Route::get('projects/{project}/team', [ProjectTeamController::class, 'index'])->name('projects.team.index');
    Route::get('projects/{project}/time-logs', [ProjectTeamController::class, 'timeLogs'])->name('projects.time-logs.index');
    // Chantier 8.4: real, working (ProjectTeamService::getProjectHours()), zero route.
    Route::get('projects/{project}/time-report', [ProjectTeamController::class, 'timeReport'])->name('projects.team.time-report');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/team', [ProjectTeamController::class, 'store'])->name('projects.team.store');
        Route::delete('projects/{project}/team/{member}', [ProjectTeamController::class, 'destroy'])->name('projects.team.destroy');
        Route::post('projects/{project}/time-logs', [ProjectTeamController::class, 'storeTimeLog'])->name('projects.time-logs.store');
        Route::post('projects/time-logs/{log}/stop', [ProjectTeamController::class, 'stopTimer'])->name('projects.time-logs.stop');
    });

    // Project-scoped epics
    Route::get('projects/{project}/epics', [EpicController::class, 'index'])->name('projects.epics.index');
    Route::get('projects/{project}/epics/{epic}', [EpicController::class, 'show'])->name('projects.epics.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/epics', [EpicController::class, 'store'])->name('projects.epics.store');
        Route::put('projects/{project}/epics/{epic}', [EpicController::class, 'update'])->name('projects.epics.update');
        Route::delete('projects/{project}/epics/{epic}', [EpicController::class, 'destroy'])->name('projects.epics.destroy');
    });

    // -------------------------------------------------------------------------
    // Sprints
    // -------------------------------------------------------------------------
    Route::get('projects/{project}/sprints', [SprintController::class, 'index'])->name('projects.sprints.index');
    Route::get('projects/{project}/sprints/{sprint}', [SprintController::class, 'show'])->name('projects.sprints.show');
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('projects/{project}/sprints/{sprint}/burndown', [SprintController::class, 'burndown'])->name('projects.sprints.burndown');
        Route::get('projects/{project}/velocity', [SprintController::class, 'velocity'])->name('projects.velocity');
        Route::get('projects/{project}/backlog', [SprintController::class, 'backlog'])->name('projects.backlog');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/sprints', [SprintController::class, 'store'])->name('projects.sprints.store');
        Route::put('projects/{project}/sprints/{sprint}', [SprintController::class, 'update'])->name('projects.sprints.update');
        Route::delete('projects/{project}/sprints/{sprint}', [SprintController::class, 'destroy'])->name('projects.sprints.destroy');
        Route::post('projects/{project}/sprints/{sprint}/start', [SprintController::class, 'start'])->name('projects.sprints.start');
        Route::post('projects/{project}/sprints/{sprint}/complete', [SprintController::class, 'complete'])->name('projects.sprints.complete');
    });

    // -------------------------------------------------------------------------
    // Automation rules
    // -------------------------------------------------------------------------
    Route::get('projects/{project}/automations', [AutomationController::class, 'index'])->name('projects.automations.index');
    Route::get('projects/{project}/automations/{automation}', [AutomationController::class, 'show'])->name('projects.automations.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('projects/{project}/automations', [AutomationController::class, 'store'])->name('projects.automations.store');
        Route::put('projects/{project}/automations/{automation}', [AutomationController::class, 'update'])->name('projects.automations.update');
        Route::delete('projects/{project}/automations/{automation}', [AutomationController::class, 'destroy'])->name('projects.automations.destroy');
    });

    // AI operations (expensive)
    Route::middleware('throttle:expensive')->prefix('projects/ai')->group(function () {
        Route::post('estimate-task', [ProjectsAIController::class, 'estimateTask']);
        Route::post('identify-risks', [ProjectsAIController::class, 'identifyRisks']);
        Route::post('status-report', [ProjectsAIController::class, 'statusReport']);
        Route::post('generate-tasks', [ProjectsAIController::class, 'generateTasks']);
        Route::post('suggest-prioritization', [ProjectsAIController::class, 'suggestPrioritization']);
    });

    // Resource capacity planning
    Route::get('allocations', [ResourceCapacityController::class, 'index'])->name('allocations.index');
    Route::get('allocations/{allocation}', [ResourceCapacityController::class, 'show'])->name('allocations.show');
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('capacity/utilization', [ResourceCapacityController::class, 'utilizationReport'])->name('capacity.utilization');
        Route::get('capacity/users/{user}/availability', [ResourceCapacityController::class, 'getAvailability'])->name('capacity.users.availability');
        Route::get('capacity/check-overallocation', [ResourceCapacityController::class, 'checkOverallocation'])->name('capacity.check-overallocation');
        Route::get('projects/{project}/demand', [ResourceCapacityController::class, 'getProjectDemand'])->name('capacity.project.demand');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('allocations', [ResourceCapacityController::class, 'store'])->name('allocations.store');
        Route::put('allocations/{allocation}', [ResourceCapacityController::class, 'update'])->name('allocations.update');
        Route::delete('allocations/{allocation}', [ResourceCapacityController::class, 'destroy'])->name('allocations.destroy');
        Route::post('capacity/suggest', [ResourceCapacityController::class, 'suggestAllocations'])->name('capacity.suggest');
        Route::post('capacity/users/{user}/leave', [ResourceCapacityController::class, 'setLeave'])->name('capacity.users.leave');
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/projects')->group(function () {
    Route::post('ai/assist', [\Modules\Projects\Http\Controllers\Api\ProjectsAiAssistController::class, 'assist'])
        ->name('projects.ai.assist');
});

// ── Phase 49: advanced project budget/KPI/risk + portfolio endpoints ───────
// (ProjectAdvancedController::index/store/show/gantt/storeTask/updateTask are
// deliberately NOT routed here — they collide with the already-active
// ProjectController/GanttController/TaskController on the same paths.)
// Chantier 8.4: was missing module:Projects/role: gating entirely — every
// other Projects route group has it. Any authenticated user of any
// tenant/role could read any project's budget/KPI/risk data.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Projects', 'role:employee,manager,admin'])->prefix('v1/projects')->group(function () {
    // Static "portfolio/..." routes MUST be registered before "{id}/..." below,
    // otherwise {id} greedily captures "portfolio" as a project id.
    Route::get('portfolio/kpis', [\Modules\Projects\Http\Controllers\Api\ProjectAdvancedController::class, 'portfolioKpis'])
        ->name('projects.advanced.portfolio-kpis');
    Route::get('portfolio/timeline', [\Modules\Projects\Http\Controllers\Api\ProjectAdvancedController::class, 'portfolioTimeline'])
        ->name('projects.advanced.portfolio-timeline');
    Route::get('portfolio/resources', [\Modules\Projects\Http\Controllers\Api\ProjectAdvancedController::class, 'portfolioResources'])
        ->name('projects.advanced.portfolio-resources');

    Route::get('{id}/budget', [\Modules\Projects\Http\Controllers\Api\ProjectAdvancedController::class, 'budget'])
        ->name('projects.advanced.budget');
    Route::get('{id}/kpis', [\Modules\Projects\Http\Controllers\Api\ProjectAdvancedController::class, 'kpis'])
        ->name('projects.advanced.kpis');
    Route::get('{id}/risks', [\Modules\Projects\Http\Controllers\Api\ProjectAdvancedController::class, 'risks'])
        ->name('projects.advanced.risks');
});
