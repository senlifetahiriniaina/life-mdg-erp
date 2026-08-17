<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Models\SourceSchema;
use Modules\Setup\Models\FieldMapping;
use Modules\Setup\Models\ImportError;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function setupUser(): User
{
    return actingAsUser('admin');
}

function createImportJob(array $overrides = []): ImportJob
{
    $user = User::first() ?? User::factory()->create();

    return ImportJob::create(array_merge([
        'tenant_id'     => $user->id,
        'name'          => 'Test Import',
        'source_type'   => 'csv',
        'target_module' => 'CRM',
        'target_entity' => 'contacts',
        'status'        => 'pending',
        'created_by'    => $user->id,
    ], $overrides));
}

// ─── Authentication ────────────────────────────────────────────────────────────

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/v1/setup/import-jobs')
        ->assertUnauthorized();
});

test('authenticated user can list import jobs', function () {
    setupUser();

    $this->getJson('/api/v1/setup/import-jobs')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

// ─── Import Job Creation ───────────────────────────────────────────────────────

test('can create a CSV import job via API', function () {
    Storage::fake('local');
    setupUser();

    $csv = UploadedFile::fake()->createWithContent(
        'leads.csv',
        "first_name,last_name,email\nAlice,Ndiaye,alice@example.com\n"
    );

    $this->postJson('/api/v1/setup/import-jobs', [
        'name'          => 'Lead Import',
        'source_type'   => 'csv',
        'target_module' => 'CRM',
        'target_entity' => 'contacts',
        'file'          => $csv,
    ])
        ->assertCreated()
        ->assertJsonPath('data.source_type', 'csv')
        ->assertJsonPath('data.status', 'pending');
});

test('creating import job without required fields returns 422', function () {
    setupUser();

    $this->postJson('/api/v1/setup/import-jobs', [
        'name' => 'Incomplete Job',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source_type', 'target_module', 'target_entity']);
});

// ─── Import Job Model ──────────────────────────────────────────────────────────

test('import job isEditable returns true for pending status', function () {
    setupUser();
    $job = createImportJob(['status' => 'pending']);

    expect($job->isEditable())->toBeTrue();
});

test('import job isEditable returns true for mapping status', function () {
    setupUser();
    $job = createImportJob(['status' => 'mapping']);

    expect($job->isEditable())->toBeTrue();
});

test('import job isEditable returns false for completed status', function () {
    setupUser();
    $job = createImportJob(['status' => 'completed']);

    expect($job->isEditable())->toBeFalse();
});

test('import job isEditable returns false for running status', function () {
    setupUser();
    $job = createImportJob(['status' => 'running']);

    expect($job->isEditable())->toBeFalse();
});

// ─── Tenant Isolation ─────────────────────────────────────────────────────────

test('import job is not visible to another tenant', function () {
    $user1 = actingAsUser('admin');
    $otherTenantUser = \App\Models\User::factory()->create();

    $job = ImportJob::create([
        'tenant_id'     => 9999,
        'name'          => 'Other Tenant Job',
        'source_type'   => 'csv',
        'target_module' => 'HR',
        'target_entity' => 'employees',
        'status'        => 'pending',
        'created_by'    => $otherTenantUser->id,
    ]);

    $response = $this->getJson('/api/v1/setup/import-jobs')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->contains($job->id))->toBeFalse();
});

// ─── Target Schemas ────────────────────────────────────────────────────────────

test('can list available target schemas', function () {
    setupUser();

    // Route is named source-schemas (matches the real ImportDataFlow.vue caller);
    // the underlying controller method listTargetSchemas() lists WideHalo's own
    // target schema catalogue, the naming mismatch is between method and route.
    $this->getJson('/api/v1/setup/source-schemas')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

// ─── Import Job Show & Status ──────────────────────────────────────────────────

test('can retrieve a specific import job by id', function () {
    $user = setupUser();
    // SetupController::tenantId() scopes by $request->user()->company_id, not
    // the user's own id — give the test user a real company and match it.
    $user->forceFill(['company_id' => \App\Models\Company::factory()->create()->id])->save();
    $job = createImportJob(['tenant_id' => $user->company_id, 'created_by' => $user->id]);

    $this->getJson("/api/v1/setup/import-jobs/{$job->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $job->id)
        ->assertJsonPath('data.name', $job->name);
});

test('import job has source_schema relationship', function () {
    setupUser();
    $job = createImportJob();

    $schema = SourceSchema::create([
        'import_job_id' => $job->id,
        'columns'       => [['name' => 'email', 'type' => 'string', 'sample_values' => ['a@b.com']]],
        'row_count'     => 10,
        'delimiter'     => ',',
    ]);

    $job->refresh();
    expect($job->sourceSchema)->not->toBeNull()
        ->and($job->sourceSchema->id)->toBe($schema->id);
});

test('import errors are associated to the correct job', function () {
    setupUser();
    $job = createImportJob(['status' => 'completed', 'failed_rows' => 1]);

    ImportError::create([
        'import_job_id' => $job->id,
        'row_number'    => 2,
        'raw_data'      => ['email' => 'bad-email'],
        'error_type'    => 'validation',
        'error_message' => 'Invalid email format',
        'field_name'    => 'email',
    ]);

    expect($job->importErrors()->count())->toBe(1)
        ->and($job->importErrors()->first()->field_name)->toBe('email');
});

test('field mappings link to import job', function () {
    setupUser();
    $job = createImportJob(['status' => 'mapping']);

    FieldMapping::create([
        'import_job_id' => $job->id,
        'source_field'  => 'prenom',
        'target_field'  => 'first_name',
        'is_confirmed'  => true,
    ]);

    expect($job->fieldMappings()->count())->toBe(1)
        ->and($job->fieldMappings()->first()->source_field)->toBe('prenom');
});
