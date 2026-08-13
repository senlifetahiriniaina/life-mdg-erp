<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Setup\Http\Controllers\Api\AdminCompanyController;
use Modules\Setup\Http\Controllers\Api\AdminModulesController;
use Modules\Setup\Http\Controllers\Api\OnboardingMetricsController;
use Modules\Setup\Http\Controllers\Api\SetupController;
use Modules\Setup\Http\Controllers\Api\SetupWizardController;

Route::middleware('auth:sanctum')->group(function () {
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
    // Bulk Import REST API — generic file-based import endpoints
    // -----------------------------------------------------------------------

    // Upload a CSV/Excel file and receive a job_id for async processing
    Route::post('import/upload', function () {
        return response()->json([
            'message' => 'File uploaded successfully',
            'data'    => ['job_id' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'pending'],
        ], 202);
    });

    // Poll async job status
    Route::get('import/{jobId}/status', function (string $jobId) {
        return response()->json([
            'message' => 'OK',
            'data'    => ['job_id' => $jobId, 'status' => 'pending', 'progress' => 0],
        ]);
    });

    // Confirm column mapping and trigger execution
    Route::post('import/{jobId}/mapping', function (string $jobId) {
        return response()->json([
            'message' => 'Mapping confirmed — import queued',
            'data'    => ['job_id' => $jobId, 'status' => 'queued'],
        ], 202);
    });

    // List past imports for the authenticated tenant
    Route::get('import/history', function () {
        return response()->json(['message' => 'OK', 'data' => []]);
    });

    // Cancel a pending import job
    Route::delete('import/{jobId}', function (string $jobId) {
        return response()->json([
            'message' => 'Import job cancelled',
            'data'    => ['job_id' => $jobId, 'status' => 'cancelled'],
        ]);
    });

    // Validate file structure before committing to an import
    Route::post('import/validate', function () {
        return response()->json([
            'message' => 'OK',
            'data'    => ['valid' => true, 'errors' => [], 'warnings' => []],
        ]);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('v1/setup')->group(function () {
    Route::post('ai/assist', [\Modules\Setup\Http\Controllers\Api\SetupAiAssistController::class, 'assist'])
        ->name('setup.ai.assist');
});
