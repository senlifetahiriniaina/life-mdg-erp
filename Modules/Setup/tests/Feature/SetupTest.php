<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Setup\Data\TargetSchemas;
use Modules\Setup\Models\FieldMapping;
use Modules\Setup\Models\ImportError;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Models\SourceSchema;
use Modules\Setup\Services\AiMappingService;
use Modules\Setup\Services\DatabaseSourceService;
use Modules\Setup\Services\FileAnalysisService;
use Modules\Setup\Services\ImportExecutorService;

uses(RefreshDatabase::class);

// ============================================================
// Helpers
// ============================================================

function makeSetupUser(int $companyId = 1): \App\Models\User
{
    return \App\Models\User::factory()->create(['company_id' => $companyId]);
}

function makeImportJob(array $overrides = []): ImportJob
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

// ============================================================
// 1. Create import job — CSV source
// ============================================================
it('creates an import job for a CSV file', function () {
    Storage::fake('local');
    $user = makeSetupUser();

    $csv = UploadedFile::fake()->createWithContent('contacts.csv', "first_name,last_name,email\nJean,Dupont,jean@example.com\n");

    $response = $this->actingAs($user)->postJson('/api/v1/setup/import-jobs', [
        'name'          => 'CRM Import',
        'source_type'   => 'csv',
        'target_module' => 'CRM',
        'target_entity' => 'contacts',
        'file'          => $csv,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.source_type', 'csv')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('setup_import_jobs', ['name' => 'CRM Import', 'source_type' => 'csv']);
});

// ============================================================
// 2. Create import job — Excel source
// ============================================================
it('creates an import job for an Excel file', function () {
    Storage::fake('local');
    $user = makeSetupUser();

    $excel = UploadedFile::fake()->create('products.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($user)->postJson('/api/v1/setup/import-jobs', [
        'name'          => 'Product Import',
        'source_type'   => 'excel',
        'target_module' => 'Inventory',
        'target_entity' => 'products',
        'file'          => $excel,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.source_type', 'excel');
});

// ============================================================
// 3. Create import job — Database source with encrypted config
// ============================================================
it('creates an import job for a database source and encrypts config', function () {
    $user = makeSetupUser();

    $response = $this->actingAs($user)->postJson('/api/v1/setup/import-jobs', [
        'name'          => 'Odoo Migration',
        'source_type'   => 'database',
        'target_module' => 'CRM',
        'target_entity' => 'contacts',
        'db_driver'     => 'pgsql',
        'db_host'       => '192.168.1.100',
        'db_port'       => 5432,
        'db_database'   => 'odoo_db',
        'db_username'   => 'odoo_user',
        'db_password'   => 'secret',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.source_type', 'database');

    // Verify the password is not stored in plaintext
    $job = ImportJob::first();
    $raw = \Illuminate\Support\Facades\DB::table('setup_import_jobs')
        ->where('id', $job->id)
        ->value('source_db_config');

    expect($raw)->not->toContain('secret');
    expect($job->source_db_config['password'])->toBe('secret'); // decrypts correctly
});

// ============================================================
// 4. File analysis — CSV delimiter detection
// ============================================================
it('detects the CSV delimiter correctly', function () {
    $service = new FileAnalysisService();

    // Semicolon-delimited (common in France/Africa)
    $semi = "nom;prenom;email\nDupont;Jean;j@example.com\n";
    $file = tempnam(sys_get_temp_dir(), 'csv_test') . '.csv';
    file_put_contents($file, $semi);

    $job = makeImportJob(['source_file_path' => basename($file)]);
    // Place file where the service can find it
    Storage::fake('local');
    Storage::disk('local')->put('imports/1/' . basename($file), $semi);

    $job->update(['source_file_path' => 'imports/1/' . basename($file)]);
    $schema = $service->analyzeFile($job);

    expect($schema->detected_delimiter)->toBe(';');
    expect(count($schema->detected_columns))->toBe(3);

    unlink($file);
});

// ============================================================
// 5. Column type inference
// ============================================================
it('infers column types correctly', function () {
    $service = new FileAnalysisService();

    expect($service->inferColumnType(['01/01/2024', '15/06/2023', '31/12/2025']))->toBe('date');
    expect($service->inferColumnType(['1234.56', '99.00', '0.50']))->toBe('decimal');
    expect($service->inferColumnType(['user@example.com', 'test@domain.fr', 'info@co.ci']))->toBe('email');
    expect($service->inferColumnType(['+261 20 22 123 45', '+33 6 12 34 56 78', '0032 4 222 33 44']))->toBe('phone');
    expect($service->inferColumnType(['42', '100', '7']))->toBe('integer');
    expect($service->inferColumnType(['hello world', 'foo', 'bar baz']))->toBe('string');
});

// ============================================================
// 6. AI mapping — degrade gracefully when no API key
// ============================================================
it('returns empty array when ANTHROPIC_API_KEY is not set', function () {
    // Ensure no API key
    \Illuminate\Support\Env::getRepository()->set('ANTHROPIC_API_KEY', '');

    $service = new AiMappingService();
    $job     = makeImportJob();
    $schema  = SourceSchema::create([
        'import_job_id'    => $job->id,
        'detected_columns' => [['name' => 'nom', 'sample_values' => ['Dupont'], 'inferred_type' => 'string']],
        'row_count'        => 10,
    ]);

    $suggestions = $service->suggestMappings($job, $schema);
    expect($suggestions)->toBeArray()->toBeEmpty();
});

// ============================================================
// 7. AI mapping — returns structured suggestions (mock HTTP)
// ============================================================
it('returns structured suggestions when API key is configured', function () {
    \Illuminate\Support\Env::getRepository()->set('ANTHROPIC_API_KEY', 'test-key');

    $mockResponse = json_encode([
        'content' => [[
            'type' => 'text',
            'text' => json_encode([
                ['source_field' => 'nom', 'target_field' => 'last_name', 'target_table' => 'crm_contacts', 'transform_type' => 'direct', 'transform_config' => null, 'confidence' => 0.95],
                ['source_field' => 'prenom', 'target_field' => 'first_name', 'target_table' => 'crm_contacts', 'transform_type' => 'direct', 'transform_config' => null, 'confidence' => 0.93],
            ]),
        ]],
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response($mockResponse, 200),
    ]);

    $service = new AiMappingService();
    $job     = makeImportJob();
    $schema  = SourceSchema::create([
        'import_job_id'    => $job->id,
        'detected_columns' => [
            ['name' => 'nom',    'sample_values' => ['Dupont'], 'inferred_type' => 'string'],
            ['name' => 'prenom', 'sample_values' => ['Jean'],   'inferred_type' => 'string'],
        ],
        'row_count' => 5,
    ]);

    $suggestions = $service->suggestMappings($job, $schema);

    expect($suggestions)->toHaveCount(2);
    expect($suggestions[0]['source_field'])->toBe('nom');
    expect($suggestions[0]['target_field'])->toBe('last_name');
    expect($suggestions[0]['confidence'])->toBe(0.95);

    $job->refresh();
    expect($job->ai_mapping_used)->toBeTrue();
    expect($job->ai_mapping_confidence)->toBeGreaterThan(0);
});

// ============================================================
// 8. Save field mappings (bulk PUT)
// ============================================================
it('saves field mappings via bulk PUT', function () {
    $user = makeSetupUser();
    $job  = makeImportJob(['status' => 'mapping', 'created_by' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/setup/import-jobs/{$job->id}/mappings", [
        'mappings' => [
            ['source_field' => 'nom', 'target_field' => 'last_name', 'target_table' => 'crm_contacts', 'transform_type' => 'direct', 'is_required' => true, 'is_confirmed' => true],
            ['source_field' => 'prenom', 'target_field' => 'first_name', 'target_table' => 'crm_contacts', 'transform_type' => 'direct', 'is_required' => true, 'is_confirmed' => false],
        ],
    ]);

    $response->assertStatus(200);
    expect(FieldMapping::where('import_job_id', $job->id)->count())->toBe(2);
});

// ============================================================
// 9. Confirm all mappings → status updates
// ============================================================
it('recognises that all required fields are confirmed', function () {
    $user = makeSetupUser();
    $job  = makeImportJob(['status' => 'mapping', 'created_by' => $user->id]);

    $this->actingAs($user)->putJson("/api/v1/setup/import-jobs/{$job->id}/mappings", [
        'mappings' => [
            ['source_field' => 'nom', 'target_field' => 'last_name', 'target_table' => 'crm_contacts', 'transform_type' => 'direct', 'is_required' => true, 'is_confirmed' => true],
        ],
    ])->assertStatus(200);

    // All required fields confirmed — job remains in mapping (ready to validate)
    $job->refresh();
    expect($job->status)->toBe('mapping');
    expect($job->fieldMappings()->where('is_required', true)->where('is_confirmed', false)->count())->toBe(0);
});

// ============================================================
// 10. Dry-run validation — finds missing required field
// ============================================================
it('returns validation error when required field has no confirmed mapping', function () {
    $user = makeSetupUser();
    $job  = makeImportJob(['status' => 'mapping', 'created_by' => $user->id]);

    // Only map non-required field
    FieldMapping::create([
        'import_job_id' => $job->id,
        'source_field'  => 'email',
        'target_field'  => 'email',
        'target_table'  => 'crm_contacts',
        'transform_type'=> 'direct',
        'is_required'   => false,
        'is_confirmed'  => true,
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/setup/import-jobs/{$job->id}/validate");

    $response->assertStatus(422)
        ->assertJsonPath('valid', false);

    // Should mention first_name or last_name as missing
    $errors = $response->json('errors');
    $fields = array_column($errors, 'field');
    expect(in_array('first_name', $fields) || in_array('last_name', $fields))->toBeTrue();
});

// ============================================================
// 11. Execute import — happy path (small CSV → CRM contacts)
// ============================================================
it('executes a CSV import successfully', function () {
    Storage::fake('local');

    $csvContent = "first_name,last_name,email\nJean,Dupont,jean@example.com\nMarie,Martin,marie@example.com\n";
    Storage::disk('local')->put('imports/1/contacts.csv', $csvContent);

    $user = makeSetupUser();
    $job  = makeImportJob([
        'source_type'       => 'csv',
        'source_file_path'  => 'imports/1/contacts.csv',
        'status'            => 'mapping',
        'created_by'        => $user->id,
        'total_rows'        => 2,
    ]);

    // Create source schema
    SourceSchema::create([
        'import_job_id'     => $job->id,
        'detected_columns'  => [
            ['name' => 'first_name', 'sample_values' => ['Jean'], 'inferred_type' => 'string'],
            ['name' => 'last_name',  'sample_values' => ['Dupont'], 'inferred_type' => 'string'],
            ['name' => 'email',      'sample_values' => ['jean@example.com'], 'inferred_type' => 'email'],
        ],
        'row_count'         => 2,
        'detected_delimiter'=> ',',
    ]);

    // Create confirmed mappings
    foreach ([
        ['first_name', 'first_name', true],
        ['last_name',  'last_name',  true],
        ['email',      'email',      false],
    ] as [$src, $tgt, $required]) {
        FieldMapping::create([
            'import_job_id' => $job->id,
            'source_field'  => $src,
            'target_field'  => $tgt,
            'target_table'  => 'crm_contacts',
            'transform_type'=> 'direct',
            'is_required'   => $required,
            'is_confirmed'  => true,
        ]);
    }

    // Mock DB table insertion (crm_contacts may not exist in test DB)
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('crm_contacts')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('insert')
        ->andReturn(true);

    $service = app(ImportExecutorService::class);
    $service->execute($job);
    $job->refresh();

    expect($job->status)->toBe('completed');
});

// ============================================================
// 12. Transform: date_format conversion
// ============================================================
it('converts date formats correctly', function () {
    $service  = app(ImportExecutorService::class);
    $mapping  = new FieldMapping(['transform_type' => 'date_format', 'transform_config' => ['format' => 'd/m/Y']]);

    $result = $service->applyTransform('15/06/2023', $mapping);
    expect($result)->toBe('2023-06-15');
});

// ============================================================
// 13. Transform: number_format strips currency symbols
// ============================================================
it('strips currency symbols and normalises decimals', function () {
    $service = app(ImportExecutorService::class);
    $mapping = new FieldMapping(['transform_type' => 'number_format', 'transform_config' => []]);

    expect($service->applyTransform('1 234,56 XOF', $mapping))->toBe(1234.56);
    expect($service->applyTransform('€2.500,00', $mapping))->toBe(2500.0);
    expect($service->applyTransform('$1,000.50', $mapping))->toBe(1000.5);
});

// ============================================================
// 14. Transform: lookup map
// ============================================================
it('maps lookup values correctly', function () {
    $service = app(ImportExecutorService::class);
    $mapping = new FieldMapping([
        'transform_type'   => 'lookup',
        'transform_config' => ['map' => ['M' => 'male', 'F' => 'female'], 'default' => 'unknown'],
    ]);

    expect($service->applyTransform('M', $mapping))->toBe('male');
    expect($service->applyTransform('F', $mapping))->toBe('female');
    expect($service->applyTransform('X', $mapping))->toBe('unknown');
});

// ============================================================
// 15. Import error recording
// ============================================================
it('records import errors correctly', function () {
    $service = app(ImportExecutorService::class);
    $job     = makeImportJob();

    $service->recordError($job, 3, ['nom' => 'Dupont'], 'email', 'invalid_format', 'Invalid email format.');

    $this->assertDatabaseHas('setup_import_errors', [
        'import_job_id' => $job->id,
        'row_number'    => 3,
        'field'         => 'email',
        'error_type'    => 'invalid_format',
    ]);

    $job->refresh();
    expect($job->failed_rows)->toBe(1);
});

// ============================================================
// 16. Get errors paginated
// ============================================================
it('returns paginated errors for a job', function () {
    $user = makeSetupUser();
    $job  = makeImportJob(['created_by' => $user->id]);

    for ($i = 1; $i <= 5; $i++) {
        ImportError::create([
            'import_job_id' => $job->id,
            'row_number'    => $i,
            'error_type'    => 'required_missing',
            'error_message' => "Row {$i} error",
        ]);
    }

    $response = $this->actingAs($user)->getJson("/api/v1/setup/import-jobs/{$job->id}/errors");

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBe(5);
});

// ============================================================
// 17. List jobs filtered by status
// ============================================================
it('filters jobs by status', function () {
    $user = makeSetupUser();
    makeImportJob(['status' => 'completed', 'created_by' => $user->id]);
    makeImportJob(['status' => 'failed',    'created_by' => $user->id]);
    makeImportJob(['status' => 'pending',   'created_by' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/setup/import-jobs?status=completed');

    $response->assertStatus(200);
    $items = $response->json('data');
    expect(count($items))->toBe(1);
    expect($items[0]['status'])->toBe('completed');
});

// ============================================================
// 18. Test DB connection — success mock
// ============================================================
it('returns success when database connection is valid', function () {
    $user = makeSetupUser();

    // Mock DatabaseSourceService::testConnection
    $mock = Mockery::mock(DatabaseSourceService::class);
    $mock->shouldReceive('testConnection')->once()->andReturn(true);
    $mock->shouldReceive('listTables')->once()->andReturn(['res_partner', 'account_move', 'product_template']);
    app()->instance(DatabaseSourceService::class, $mock);

    $response = $this->actingAs($user)->postJson('/api/v1/setup/test-connection', [
        'driver'   => 'pgsql',
        'host'     => '192.168.1.100',
        'database' => 'odoo_db',
        'username' => 'odoo_user',
        'password' => 'secret',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);
    expect($response->json('tables'))->toHaveCount(3);
});

// ============================================================
// 19. Test DB connection — failure returns 422 with message
// ============================================================
it('returns 422 when database connection fails', function () {
    $user = makeSetupUser();

    $mock = Mockery::mock(DatabaseSourceService::class);
    $mock->shouldReceive('testConnection')->once()
        ->andThrow(new \RuntimeException('Database connection failed: Access denied'));
    app()->instance(DatabaseSourceService::class, $mock);

    $response = $this->actingAs($user)->postJson('/api/v1/setup/test-connection', [
        'driver'   => 'mysql',
        'host'     => '10.0.0.1',
        'database' => 'erp_db',
        'username' => 'user',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
    expect($response->json('message'))->toContain('Database connection failed');
});

// ============================================================
// 20. List target schemas returns all modules
// ============================================================
it('returns all available target schemas', function () {
    $user     = makeSetupUser();
    $response = $this->actingAs($user)->getJson('/api/v1/setup/source-schemas');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(7);

    $entities = array_column($data, 'entity');
    expect($entities)->toContain('contacts');
    expect($entities)->toContain('employees');
    expect($entities)->toContain('products');
    expect($entities)->toContain('suppliers');
    expect($entities)->toContain('accounts');
    expect($entities)->toContain('invoices');
});

// ============================================================
// 21. Unauthenticated request → 401
// ============================================================
it('returns 401 for unauthenticated requests', function () {
    $this->getJson('/api/v1/setup/import-jobs')->assertStatus(401);
    $this->postJson('/api/v1/setup/import-jobs')->assertStatus(401);
    $this->getJson('/api/v1/setup/source-schemas')->assertStatus(401);
});

// ============================================================
// 22. Tenant isolation — job for different tenant not visible
// ============================================================
it('does not expose jobs belonging to another tenant', function () {
    $userA = makeSetupUser(companyId: 1);
    $userB = makeSetupUser(companyId: 2);

    $jobA = makeImportJob(['tenant_id' => 1, 'created_by' => $userA->id]);

    // userB (tenant 2) should NOT see jobA (tenant 1)
    $response = $this->actingAs($userB)->getJson("/api/v1/setup/import-jobs/{$jobA->id}");
    $response->assertStatus(404);

    // List endpoint should also return 0 results for tenant 2
    $list = $this->actingAs($userB)->getJson('/api/v1/setup/import-jobs');
    $list->assertStatus(200);
    expect($list->json('data'))->toBeEmpty();
});
