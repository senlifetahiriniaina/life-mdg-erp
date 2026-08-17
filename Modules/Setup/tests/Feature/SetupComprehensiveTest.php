<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Setup\Models\FieldMapping;
use Modules\Setup\Models\ImportError;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Models\OnboardingSession;
use Modules\Setup\Models\SourceSchema;
use Modules\Setup\Services\AiMappingService;
use Modules\Setup\Services\FileAnalysisService;
use Modules\Setup\Services\ImportExecutorService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function setupJob(array $overrides = []): ImportJob
{
    return ImportJob::factory()->create(array_merge([
        'tenant_id'     => 1,
        'name'          => 'Test Import',
        'source_type'   => 'csv',
        'target_module' => 'CRM',
        'target_entity' => 'contacts',
        'status'        => 'pending',
        'created_by'    => 1,
    ], $overrides));
}

/**
 * Stores $csvContent on the (faked) 'local' disk and returns an ImportJob
 * pointing at it via source_file_path — the real precondition
 * FileAnalysisService/ImportExecutorService expect (see resolveFilePath()).
 */
function storeCsvJob(string $csvContent, array $overrides = []): ImportJob
{
    $path = 'imports/' . uniqid('test_', true) . '.csv';
    Storage::disk('local')->put($path, $csvContent);

    return setupJob(array_merge([
        'source_type'      => 'csv',
        'source_file_path' => $path,
    ], $overrides));
}

// ─── AiMappingService ─────────────────────────────────────────────────────────
//
// Note: two scenarios from the original phantom-API test set were dropped
// rather than rewritten, because no real capability under that name exists:
//   - "detects entity type from column headers": AiMappingService (the real,
//     routed mapping pipeline behind SetupController) never infers the
//     target entity — the caller picks target_module/target_entity up front
//     when creating the ImportJob. A `detectEntityType()`-shaped method does
//     exist on `Modules\Setup\Services\AiDataImportService::analyzeFile()`,
//     but that service/its `DataImportController` are never registered in
//     `Modules/Setup/routes/api.php` — a separate, unwired pipeline, not a
//     same-feature duplicate to redirect this test to.
//   - "validates a mapping configuration for required fields": there is no
//     `validateMapping()` service method. The real "does this mapping cover
//     all required target fields" check is inlined in
//     `SetupController::validateJob()` (POST .../import-jobs/{id}/validate),
//     already exercised by the "Import Job API" describe block below.
describe('AiMappingService', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(AiMappingService::class);
    });

    test('suggests column mappings for CSV source columns', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    ['source_field' => 'first_name', 'target_field' => 'first_name', 'target_table' => 'crm_contacts', 'transform_type' => 'direct', 'confidence' => 0.98],
                    ['source_field' => 'email',      'target_field' => 'email',      'target_table' => 'crm_contacts', 'transform_type' => 'direct', 'confidence' => 0.99],
                ])]],
            ], 200),
        ]);

        $job    = setupJob();
        $schema = SourceSchema::factory()->create([
            'import_job_id'    => $job->id,
            'detected_columns' => [
                ['name' => 'first_name', 'sample_values' => ['Jean'], 'inferred_type' => 'string'],
                ['name' => 'email',      'sample_values' => ['jean@test.com'], 'inferred_type' => 'email'],
                ['name' => 'tel',        'sample_values' => ['+221771234567'], 'inferred_type' => 'phone'],
            ],
        ]);

        $result = $this->service->suggestMappings($job, $schema);

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(2)
            ->and($result[0])->toHaveKeys(['source_field', 'target_field', 'confidence']);
    });

    test('returns empty suggestions when Claude API is unavailable (graceful degradation)', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([], 500),
        ]);

        $job    = setupJob();
        $schema = SourceSchema::factory()->create([
            'import_job_id'    => $job->id,
            'detected_columns' => [['name' => 'nom'], ['name' => 'prenom'], ['name' => 'courriel']],
        ]);

        $result = $this->service->suggestMappings($job, $schema);

        expect($result)->toBeArray()->toBeEmpty();
    });

    test('handles empty source column list gracefully', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '[]']],
            ], 200),
        ]);

        $job    = setupJob();
        $schema = SourceSchema::factory()->create([
            'import_job_id'    => $job->id,
            'detected_columns' => [],
        ]);

        $result = $this->service->suggestMappings($job, $schema);

        expect($result)->toBeArray()->toBeEmpty();
    });
});

// ─── FileAnalysisService ──────────────────────────────────────────────────────

describe('FileAnalysisService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(FileAnalysisService::class);
        Storage::fake('local');
    });

    test('analyzes CSV structure and returns column headers', function () {
        $csvContent = "first_name,last_name,email\nJean,Dupont,jean@example.com\nMarie,Martin,marie@example.com\n";
        $job = storeCsvJob($csvContent);

        $schema = $this->service->analyzeFile($job);

        expect($schema)->toBeInstanceOf(SourceSchema::class)
            ->and(array_column($schema->detected_columns, 'name'))->toBe(['first_name', 'last_name', 'email'])
            ->and($schema->row_count)->toBe(2);
    });

    test('detects comma delimiter in CSV file', function () {
        $csvContent = "col1,col2,col3\nval1,val2,val3\n";
        $job = storeCsvJob($csvContent);

        $result = $this->service->analyzeCsv($job);

        expect($result['delimiter'])->toBe(',');
    });

    test('detects semicolon delimiter in CSV file', function () {
        $csvContent = "col1;col2;col3\nval1;val2;val3\n";
        $job = storeCsvJob($csvContent);

        $result = $this->service->analyzeCsv($job);

        expect($result['delimiter'])->toBe(';');
    });

    test('counts rows in CSV file', function () {
        $csvContent = "col1,col2\nrow1a,row1b\nrow2a,row2b\nrow3a,row3b\n";
        $job = storeCsvJob($csvContent);

        $result = $this->service->analyzeCsv($job);

        expect($result['row_count'])->toBeGreaterThanOrEqual(3);
    });

    test('detects encoding of CSV file', function () {
        $csvContent = "name,email\nJean,jean@test.com\n";
        $job = storeCsvJob($csvContent);

        $result = $this->service->analyzeCsv($job);

        expect($result['encoding'])->toBeString();
    });
});

// ─── ImportExecutorService ────────────────────────────────────────────────────
//
// Note: "handles dry-run validation without persisting data" (phantom
// `ImportExecutorService::dryRun()`) was dropped rather than rewritten —
// the service has no dry-run method. The real dry-run/validation capability
// is `SetupController::validateJob()` (POST .../import-jobs/{id}/validate),
// already exercised by the "Import Job API" describe block below.
describe('ImportExecutorService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ImportExecutorService::class);
        Storage::fake('local');
    });

    test('executes import and updates job status to completed', function () {
        $csvContent = "first_name,last_name,email\nJean,Dupont,jean@example.com\n";
        $job = storeCsvJob($csvContent, [
            'status'        => 'validated',
            'target_module' => 'CRM',
            'target_entity' => 'contacts',
        ]);

        // Real analysis step — populates the SourceSchema (detected_columns,
        // delimiter) the executor reads while streaming the file.
        app(FileAnalysisService::class)->analyzeFile($job);

        foreach (['first_name', 'last_name', 'email'] as $field) {
            FieldMapping::factory()->create([
                'import_job_id'  => $job->id,
                'source_field'   => $field,
                'target_field'   => $field,
                'target_table'   => 'crm_contacts',
                'transform_type' => 'direct',
                'is_confirmed'   => true,
            ]);
        }

        $this->service->execute($job);

        expect($job->fresh()->status)->toBe('completed');
    });

    test('tracks import progress via the real ImportJob::getProgressPercent() helper', function () {
        // ImportExecutorService itself has no getProgress() method — progress
        // is exposed by ImportJob::getProgressPercent(), computed from the
        // imported_rows/total_rows columns the service updates during execute().
        $job = setupJob(['status' => 'importing', 'total_rows' => 100, 'imported_rows' => 40]);

        expect($job->getProgressPercent())->toBe(40.0);
    });

    test('records import errors when rows fail validation', function () {
        $job = setupJob(['status' => 'validated']);

        $this->service->recordError($job, 5, ['email' => 'bad-email'], 'email', 'invalid_format', 'Invalid email format.');

        $error = ImportError::where('import_job_id', $job->id)->first();

        expect($error)->not->toBeNull()
            ->and($error->row_number)->toBe(5);
    });

    test('returns error list for a job via the real importErrors relationship', function () {
        // ImportExecutorService has no getErrors() method — errors are
        // queried through ImportJob::importErrors(), the same relationship
        // SetupController::listErrors() (GET .../import-jobs/{id}/errors) uses.
        $job = setupJob(['status' => 'failed']);
        ImportError::factory()->count(3)->create(['import_job_id' => $job->id]);

        $errors = $job->importErrors()->get();

        expect($errors)->toHaveCount(3);
    });
});

// ─── Setup Wizard API endpoints ───────────────────────────────────────────────

describe('Setup Wizard API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('GET /api/v1/setup/wizard/state returns wizard state', function () {
        $response = $this->getJson('/api/v1/setup/wizard/state');
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/wizard/company saves company info', function () {
        $response = $this->postJson('/api/v1/setup/wizard/company', [
            'company_name'  => 'ACME Sénégal SARL',
            'country_code'  => 'SN',
            'currency_code' => 'XOF',
            'industry'      => 'retail',
        ]);
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/wizard/modules saves selected modules', function () {
        // Step 3 requires the company profile from step 1 to already exist.
        $this->postJson('/api/v1/setup/wizard/company', [
            'company_name' => 'ACME Sénégal SARL',
            'country_code' => 'SN',
        ])->assertStatus(200);

        $response = $this->postJson('/api/v1/setup/wizard/modules', [
            'modules' => ['CRM', 'HR', 'Inventory'],
        ]);
        $response->assertStatus(200);
    });

    test('GET /api/v1/setup/wizard/modules/catalog returns module catalog', function () {
        $response = $this->getJson('/api/v1/setup/wizard/modules/catalog');
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/wizard/complete marks wizard as complete', function () {
        // Step 6 requires the company profile from step 1 to already exist.
        $this->postJson('/api/v1/setup/wizard/company', [
            'company_name' => 'ACME Sénégal SARL',
            'country_code' => 'SN',
        ])->assertStatus(200);

        $response = $this->postJson('/api/v1/setup/wizard/complete', []);
        $response->assertStatus(200);
    });
});

// ─── Import Job API endpoints ─────────────────────────────────────────────────

describe('Import Job API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        // SetupController::tenantId() resolves tenant from the authenticated
        // user's company_id, not from ImportJob::tenant_id directly.
        // users.company_id has a real FK to companies.id, so a real Company
        // row is required (a bare literal id would violate the constraint).
        $this->company = \App\Models\Company::factory()->create();
        $this->user->update(['company_id' => $this->company->id]);
    });

    test('GET /api/v1/setup/import-jobs lists jobs for tenant', function () {
        setupJob(['tenant_id' => $this->company->id]);
        $response = $this->getJson('/api/v1/setup/import-jobs');
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/import-jobs creates a new job', function () {
        Storage::fake('local');
        $csv = UploadedFile::fake()->createWithContent('data.csv', "name,email\nJean,jean@test.com\n");

        $response = $this->postJson('/api/v1/setup/import-jobs', [
            'name'          => 'CRM Import',
            'source_type'   => 'csv',
            'target_module' => 'CRM',
            'target_entity' => 'contacts',
            'file'          => $csv,
        ]);
        $response->assertStatus(201);
    });

    test('GET /api/v1/setup/import-jobs/{id} returns job detail', function () {
        $job = setupJob(['tenant_id' => $this->company->id]);
        $response = $this->getJson("/api/v1/setup/import-jobs/{$job->id}");
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/import-jobs/{id}/validate performs dry-run', function () {
        $job = setupJob(['tenant_id' => $this->company->id, 'status' => 'mapped']);
        // CRM/contacts' required target fields (see TargetSchemas::getSchema) must
        // have a confirmed mapping before a dry-run validates cleanly.
        foreach (['first_name', 'last_name'] as $field) {
            FieldMapping::create([
                'import_job_id' => $job->id,
                'source_field'  => $field,
                'target_field'  => $field,
                'is_required'   => true,
                'is_confirmed'  => true,
            ]);
        }
        $response = $this->postJson("/api/v1/setup/import-jobs/{$job->id}/validate");
        $response->assertStatus(200);
    });

    test('GET /api/v1/setup/source-schemas returns target schema catalogue', function () {
        $response = $this->getJson('/api/v1/setup/source-schemas');
        $response->assertStatus(200);
    });
});

// ─── Onboarding Metrics ───────────────────────────────────────────────────────

describe('Onboarding Metrics API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        // OnboardingMetricsController::tenantId() resolves tenant from company_id.
        // users.company_id has a real FK to companies.id, so a real Company
        // row is required (a bare literal id would violate the constraint).
        $this->company = \App\Models\Company::factory()->create();
        $this->user->update(['company_id' => $this->company->id]);
    });

    test('POST /api/v1/setup/onboarding/start creates a new session', function () {
        $response = $this->postJson('/api/v1/setup/onboarding/start', [
            'source_type' => 'manual',
        ]);
        $response->assertStatus(201);
    });

    test('GET /api/v1/setup/onboarding/stats returns funnel stats', function () {
        $response = $this->getJson('/api/v1/setup/onboarding/stats');
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/onboarding/{id}/step records a step event', function () {
        $session = OnboardingSession::factory()->create(['tenant_id' => $this->company->id]);
        $response = $this->postJson("/api/v1/setup/onboarding/{$session->id}/step", [
            'step'  => 1,
            'event' => 'completed',
        ]);
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/onboarding/{id}/complete marks session done', function () {
        $session = OnboardingSession::factory()->create(['tenant_id' => $this->company->id]);
        $response = $this->postJson("/api/v1/setup/onboarding/{$session->id}/complete");
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/onboarding/{id}/abandon marks session abandoned', function () {
        $session = OnboardingSession::factory()->create(['tenant_id' => $this->company->id]);
        $response = $this->postJson("/api/v1/setup/onboarding/{$session->id}/abandon", [
            'at_step' => 3,
        ]);
        $response->assertStatus(200);
    });
});
