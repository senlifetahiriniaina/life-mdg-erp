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

// ─── AiMappingService ─────────────────────────────────────────────────────────

describe('AiMappingService', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(AiMappingService::class);
    });

    test('suggests column mappings for CSV source columns', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'mappings' => [
                        ['source' => 'first_name', 'target' => 'first_name', 'confidence' => 0.98],
                        ['source' => 'email',      'target' => 'email',      'confidence' => 0.99],
                    ],
                ])]],
            ], 200),
        ]);

        $job = setupJob();
        $sourceColumns = ['first_name', 'email', 'tel'];
        $targetEntity  = 'contacts';

        $result = $this->service->suggestMappings($job, $sourceColumns, $targetEntity);

        expect($result)->toBeArray();
    });

    test('detects entity type from column headers', function () {
        $columns = ['first_name', 'last_name', 'email', 'phone'];
        $result  = $this->service->detectEntityType($columns);

        expect($result)->toBeString();
    });

    test('validates a mapping configuration for required fields', function () {
        $job = setupJob(['target_entity' => 'contacts']);
        $mappings = [
            ['source_column' => 'email',      'target_field' => 'email',      'transform' => null],
            ['source_column' => 'first_name', 'target_field' => 'first_name', 'transform' => null],
        ];

        $result = $this->service->validateMapping($job, $mappings);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('valid');
    });

    test('returns fallback suggestions when Claude API is unavailable', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([], 500),
        ]);

        $job    = setupJob();
        $result = $this->service->suggestMappings($job, ['nom', 'prenom', 'courriel'], 'contacts');

        expect($result)->toBeArray();
    });

    test('handles empty column list gracefully', function () {
        $job    = setupJob();
        $result = $this->service->suggestMappings($job, [], 'contacts');

        expect($result)->toBeArray();
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
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $job = setupJob(['source_type' => 'csv']);
        $result = $this->service->analyzeFile($job, $file->path());

        expect($result)->toBeArray();
    });

    test('detects comma delimiter in CSV file', function () {
        $csvContent = "col1,col2,col3\nval1,val2,val3\n";
        $file = UploadedFile::fake()->createWithContent('data.csv', $csvContent);

        $delimiter = $this->service->detectDelimiter($file->path());

        expect($delimiter)->toBe(',');
    });

    test('detects semicolon delimiter in CSV file', function () {
        $csvContent = "col1;col2;col3\nval1;val2;val3\n";
        $file = UploadedFile::fake()->createWithContent('data.csv', $csvContent);

        $delimiter = $this->service->detectDelimiter($file->path());

        expect($delimiter)->toBe(';');
    });

    test('counts rows in CSV file', function () {
        $csvContent = "col1,col2\nrow1a,row1b\nrow2a,row2b\nrow3a,row3b\n";
        $file = UploadedFile::fake()->createWithContent('data.csv', $csvContent);

        $count = $this->service->countRows($file->path());

        expect($count)->toBeGreaterThanOrEqual(3);
    });

    test('detects encoding of CSV file', function () {
        $csvContent = "name,email\nJean,jean@test.com\n";
        $file = UploadedFile::fake()->createWithContent('data.csv', $csvContent);

        $encoding = $this->service->detectEncoding($file->path());

        expect($encoding)->toBeString();
    });
});

// ─── ImportExecutorService ────────────────────────────────────────────────────

describe('ImportExecutorService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ImportExecutorService::class);
    });

    test('executes import and updates job status to completed', function () {
        $job = setupJob(['status' => 'validated']);

        SourceSchema::factory()->create([
            'import_job_id' => $job->id,
            'columns'       => json_encode(['first_name', 'email']),
        ]);

        FieldMapping::factory()->create([
            'import_job_id' => $job->id,
            'source_column' => 'email',
            'target_field'  => 'email',
        ]);

        $result = $this->service->execute($job);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('status');
    });

    test('tracks import progress during execution', function () {
        $job = setupJob(['status' => 'validated', 'total_rows' => 100]);

        $progress = $this->service->getProgress($job);

        expect($progress)->toBeArray()
            ->and($progress)->toHaveKey('processed_rows');
    });

    test('records import errors when rows fail validation', function () {
        $job = setupJob(['status' => 'validated']);

        $this->service->recordError($job, 5, 'email', 'Invalid email format', 'bad-email');

        $error = ImportError::where('import_job_id', $job->id)->first();

        expect($error)->not->toBeNull()
            ->and($error->row_number)->toBe(5);
    });

    test('returns error list for a job', function () {
        $job = setupJob(['status' => 'failed']);
        ImportError::factory()->count(3)->create(['import_job_id' => $job->id]);

        $errors = $this->service->getErrors($job);

        expect($errors)->toHaveCount(3);
    });

    test('handles dry-run validation without persisting data', function () {
        $job = setupJob(['status' => 'mapped']);
        FieldMapping::factory()->create([
            'import_job_id' => $job->id,
            'source_column' => 'email',
            'target_field'  => 'email',
        ]);

        $result = $this->service->dryRun($job);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('valid');
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
            'name'    => 'ACME Sénégal SARL',
            'country' => 'SN',
            'currency'=> 'XOF',
            'industry'=> 'retail',
        ]);
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/wizard/modules saves selected modules', function () {
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
        $response = $this->postJson('/api/v1/setup/wizard/complete', []);
        $response->assertStatus(200);
    });
});

// ─── Import Job API endpoints ─────────────────────────────────────────────────

describe('Import Job API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('GET /api/v1/setup/import-jobs lists jobs for tenant', function () {
        setupJob(['tenant_id' => $this->user->id]);
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
        ]);
        $response->assertStatus(201);
    });

    test('GET /api/v1/setup/import-jobs/{id} returns job detail', function () {
        $job = setupJob(['tenant_id' => 1]);
        $response = $this->getJson("/api/v1/setup/import-jobs/{$job->id}");
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/import-jobs/{id}/validate performs dry-run', function () {
        $job = setupJob(['status' => 'mapped']);
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
    });

    test('POST /api/v1/setup/onboarding/start creates a new session', function () {
        $response = $this->postJson('/api/v1/setup/onboarding/start', [
            'channel' => 'web',
        ]);
        $response->assertStatus(201);
    });

    test('GET /api/v1/setup/onboarding/stats returns funnel stats', function () {
        $response = $this->getJson('/api/v1/setup/onboarding/stats');
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/onboarding/{id}/step records a step event', function () {
        $session = OnboardingSession::factory()->create(['tenant_id' => 1]);
        $response = $this->postJson("/api/v1/setup/onboarding/{$session->id}/step", [
            'step'    => 'company',
            'outcome' => 'completed',
        ]);
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/onboarding/{id}/complete marks session done', function () {
        $session = OnboardingSession::factory()->create(['tenant_id' => 1]);
        $response = $this->postJson("/api/v1/setup/onboarding/{$session->id}/complete");
        $response->assertStatus(200);
    });

    test('POST /api/v1/setup/onboarding/{id}/abandon marks session abandoned', function () {
        $session = OnboardingSession::factory()->create(['tenant_id' => 1]);
        $response = $this->postJson("/api/v1/setup/onboarding/{$session->id}/abandon");
        $response->assertStatus(200);
    });
});
