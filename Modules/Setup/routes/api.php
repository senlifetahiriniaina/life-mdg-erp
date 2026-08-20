<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Setup\Http\Controllers\Api\AdminCompanyController;
use Modules\Setup\Http\Controllers\Api\AdminModulesController;
use Modules\Setup\Http\Controllers\Api\DataImportController;
use Modules\Setup\Http\Controllers\Api\OnboardingMetricsController;
use Modules\Setup\Http\Controllers\Api\SetupController;
use Modules\Setup\Http\Controllers\Api\SetupThresholdsController;
use Modules\Setup\Http\Controllers\Api\SetupWizardController;

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->group(function () {
    // -----------------------------------------------------------------------
    // Setup Wizard — company onboarding (6 steps)
    // -----------------------------------------------------------------------

    Route::prefix('wizard')->group(function () {
        Route::get('/state', [SetupWizardController::class, 'getState']);
        Route::post('/company', [SetupWizardController::class, 'saveCompany']);
        Route::post('/admin', [SetupWizardController::class, 'saveAdmin']);
        Route::post('/modules', [SetupWizardController::class, 'saveModules']);
        Route::post('/workflows', [SetupWizardController::class, 'saveWorkflows']);
        Route::post('/apps', [SetupWizardController::class, 'saveApps']);
        Route::post('/complete', [SetupWizardController::class, 'complete']);
        Route::get('/modules/catalog', [SetupWizardController::class, 'getModuleCatalog']);

        Route::middleware('role:admin,super-admin')->group(function () {
            Route::get('/thresholds/{module}', [SetupThresholdsController::class, 'show']);
        });
    });

    // -----------------------------------------------------------------------
    // Admin routes — module & company management (admin/super-admin only)
    // -----------------------------------------------------------------------

    Route::prefix('v1/admin')->middleware(['role:admin,super-admin'])->group(function () {
        Route::get('modules', [AdminModulesController::class, 'index']);
        Route::put('modules/{module}', [AdminModulesController::class, 'update']);
        Route::post('modules/bulk', [AdminModulesController::class, 'bulk']);

        Route::get('company', [AdminCompanyController::class, 'show']);
        Route::put('company', [AdminCompanyController::class, 'update']);
        Route::post('company/logo', [AdminCompanyController::class, 'uploadLogo']);
    });


    // Chantier 8.5sv: this block (import jobs, target schemas, connection
    // tester, onboarding metrics, bulk import stubs) previously had no
    // module/role gate at all — any authenticated user of any tenant could
    // reach it, same RBAC-hole pattern already fixed for Inventory/HR/etc.
    // elsewhere in this app.
    Route::middleware(['module:Setup', 'role:employee,admin,super-admin'])->group(function () {
        // -----------------------------------------------------------------------
        // Import Jobs
        // -----------------------------------------------------------------------

        // List all jobs for the authenticated tenant
        Route::get('import-jobs', [SetupController::class, 'listJobs']);

        // Create a new import job (file upload or DB config)
        Route::post('import-jobs', [SetupController::class, 'createJob']);

        // Get a specific job with schema + mappings
        Route::get('import-jobs/{id}', [SetupController::class, 'showJob']);

        // Trigger file analysis (builds SourceSchema)
        Route::post('import-jobs/{id}/analyze', [SetupController::class, 'analyzeJob']);

        // Request AI field mapping suggestions
        Route::post('import-jobs/{id}/suggest-mappings', [SetupController::class, 'suggestMappings']);

        // Save / update field mappings (bulk replace)
        Route::put('import-jobs/{id}/mappings', [SetupController::class, 'saveMappings']);

        // Dry-run validation — check required fields are mapped
        Route::post('import-jobs/{id}/validate', [SetupController::class, 'validateJob']);

        // Execute the actual import
        Route::post('import-jobs/{id}/execute', [SetupController::class, 'executeJob']);

        // Paginated import errors for a job
        Route::get('import-jobs/{id}/errors', [SetupController::class, 'listErrors']);

        // -----------------------------------------------------------------------
        // Target Schema catalogue
        // -----------------------------------------------------------------------

        // List all available import targets (module + entity + fields)
        Route::get('source-schemas', [SetupController::class, 'listTargetSchemas']);

        // -----------------------------------------------------------------------
        // External DB connection tester
        // -----------------------------------------------------------------------

        Route::post('test-connection', [SetupController::class, 'testConnection']);

        // -----------------------------------------------------------------------
        // Onboarding Metrics (Simplicity First funnel tracking)
        // -----------------------------------------------------------------------

        // Start a new onboarding session (Step 1 of the wizard)
        Route::post('onboarding/start', [OnboardingMetricsController::class, 'start']);

        // Get funnel stats for the tenant (optional ?days= query param)
        Route::get('onboarding/stats', [OnboardingMetricsController::class, 'stats']);

        // Export sessions as CSV
        Route::get('onboarding/stats/export', [OnboardingMetricsController::class, 'exportCsv']);

        // Record a step-level event on an active session
        Route::post('onboarding/{id}/step', [OnboardingMetricsController::class, 'recordStep']);

        // Mark session as successfully completed
        Route::post('onboarding/{id}/complete', [OnboardingMetricsController::class, 'complete']);

        // Mark session as abandoned (user left wizard early)
        Route::post('onboarding/{id}/abandon', [OnboardingMetricsController::class, 'abandon']);

        // -----------------------------------------------------------------------
        // AI-assisted data import pipeline (DataImportController)
        //
        // Chantier 8.5sv: this replaces 6 dead stub closures that returned
        // hardcoded static JSON (job_id from Str::uuid(), status always
        // 'pending'/'queued', import/history always []) — a fully-built real
        // pipeline (DataImportController + AiDataImportService + ImportDataJob)
        // already existed with zero routes anywhere in the app.
        // -----------------------------------------------------------------------

        // Upload a file and receive AI-suggested column mappings
        Route::post('import/analyze', [DataImportController::class, 'analyze']);

        // Validate a column mapping and receive a data-quality report
        Route::post('import/validate', [DataImportController::class, 'validate']);

        // Start an asynchronous import job
        Route::post('import/execute', [DataImportController::class, 'execute']);

        // Poll the progress of an import job
        Route::get('import/status/{jobId}', [DataImportController::class, 'status']);

        // List supported entity types with their field schemas
        Route::get('import/templates', [DataImportController::class, 'templates']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
// Chantier 19 Lot 3: this block had its own `->prefix('v1/setup')` on top
// of the `api/v1/setup` prefix Modules\Setup\Providers\RouteServiceProvider
// already applies to the whole file — every other route in this file relies
// solely on the provider's prefix (confirmed: `wizard/state` correctly
// resolves to `api/v1/setup/wizard/state`), so this one block doubled up to
// `api/v1/setup/v1/setup/ai/assist` instead of the intended
// `api/v1/setup/ai/assist` — confirmed via `php artisan route:list` and a
// real 404 on the documented URL. Matches every sibling module's AI-assist
// route shape (`api/v1/{module}/ai/assist`) once the redundant prefix is
// dropped.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->group(function () {
    Route::post('ai/assist', [\Modules\Setup\Http\Controllers\Api\SetupAiAssistController::class, 'assist'])
        ->name('setup.ai.assist');
});
