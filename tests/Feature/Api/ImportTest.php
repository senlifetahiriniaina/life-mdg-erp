<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Jobs\ExtractAndMapImportJob;
use Modules\Core\Models\ImportJob;
use Modules\Core\Models\ImportRow;

uses(RefreshDatabase::class);

// ── Auth guard ────────────────────────────────────────────────────────────────

test('unauthenticated gets 401 on upload', function () {
    $this->postJson('/api/v1/import/upload')->assertUnauthorized();
});

test('unauthenticated gets 401 on job list', function () {
    $this->getJson('/api/v1/import/jobs')->assertUnauthorized();
});

// ── Validation ────────────────────────────────────────────────────────────────

test('unsupported file type gets 422', function () {
     $user = actingAsUser('employee');
    Storage::fake('local');
    Queue::fake();
        $response = $this
        ->postJson('/api/v1/import/upload', [
            'file'          => UploadedFile::fake()->create('data.txt', 10, 'text/plain'),
            'target_entity' => 'contact',
        ])
        ->assertUnprocessable();
});

test('file too large gets 422', function () {
     $user = actingAsUser('employee');
    Storage::fake('local');
    Queue::fake();
        $response = $this
        ->postJson('/api/v1/import/upload', [
            'file'          => UploadedFile::fake()->create('data.csv', 25000, 'text/csv'),
            'target_entity' => 'contact',
        ])
        ->assertUnprocessable();
});

test('invalid target entity gets 422', function () {
     $user = actingAsUser('employee');
    Storage::fake('local');
    Queue::fake();
        $response = $this
        ->postJson('/api/v1/import/upload', [
            'file'          => UploadedFile::fake()->create('data.csv', 10, 'text/csv'),
            'target_entity' => 'unknown_entity',
        ])
        ->assertUnprocessable();
});

// ── Upload ────────────────────────────────────────────────────────────────────

test('can upload CSV file and get 202 with job', function () {
     $user = actingAsUser('employee');
    Storage::fake('local');
    Queue::fake();

    $csv = UploadedFile::fake()->createWithContent('contacts.csv', "first_name,last_name,email\nJohn,Doe,john@example.com\n");
    $response = $this
        ->postJson('/api/v1/import/upload', [
            'file'          => $csv,
            'target_entity' => 'contact',
        ])
        ->assertStatus(202)
        ->assertJsonStructure(['id', 'filename', 'file_type', 'target_entity', 'status']);

    $this->assertEquals('contact', $response->json('target_entity'));
    $this->assertEquals('uploaded', $response->json('status'));

    Queue::assertPushed(ExtractAndMapImportJob::class);
});

test('upload creates ImportJob record in database', function () {
     $user = actingAsUser('employee');
    Storage::fake('local');
    Queue::fake();

    $csv = UploadedFile::fake()->createWithContent('products.csv', "name,sku,price\nWidget,WID-001,9.99\n");
        $response = $this
        ->postJson('/api/v1/import/upload', [
            'file'          => $csv,
            'target_entity' => 'product',
        ])
        ->assertStatus(202);

    $this->assertDatabaseHas('core_import_jobs', [
        'user_id'       => $user->id,
        'target_entity' => 'product',
        'status'        => 'uploaded',
    ]);
});

// ── Status polling ────────────────────────────────────────────────────────────

test('status polling returns correct structure', function () {
     $user = actingAsUser('employee');

    $job = ImportJob::create([
        'user_id'       => $user->id,
        'filename'      => 'test.csv',
        'file_path'     => 'imports/test.csv',
        'file_type'     => 'csv',
        'target_entity' => 'contact',
        'status'        => 'extracted',
        'total_rows'    => 5,
    ]);
        $response = $this
        ->getJson("/api/v1/import/jobs/{$job->id}")
        ->assertOk()
        ->assertJsonStructure(['id', 'status', 'filename', 'target_entity', 'total_rows', 'processed_rows', 'failed_rows', 'preview_rows']);
});

test('cannot view another user job', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $job = ImportJob::create([
        'user_id'       => $user2->id,
        'filename'      => 'private.csv',
        'file_path'     => 'imports/private.csv',
        'file_type'     => 'csv',
        'target_entity' => 'contact',
        'status'        => 'uploaded',
    ]);

    $this->actingAs($user1, 'sanctum')
        ->getJson("/api/v1/import/jobs/{$job->id}")
        ->assertForbidden();
});

// ── Mapping ───────────────────────────────────────────────────────────────────

test('can confirm column mapping', function () {
     $user = actingAsUser('employee');

    $job = ImportJob::create([
        'user_id'       => $user->id,
        'filename'      => 'test.csv',
        'file_path'     => 'imports/test.csv',
        'file_type'     => 'csv',
        'target_entity' => 'contact',
        'status'        => 'extracted',
    ]);

    ImportRow::create([
        'import_job_id' => $job->id,
        'row_index'     => 0,
        'raw_data'      => ['First Name' => 'Jane', 'Last Name' => 'Smith', 'Email' => 'jane@example.com'],
        'status'        => 'pending',
    ]);

    $mapping = [
        'First Name' => 'first_name',
        'Last Name'  => 'last_name',
        'Email'      => 'email',
    ];
        $response = $this
        ->putJson("/api/v1/import/jobs/{$job->id}/mapping", ['column_mapping' => $mapping])
        ->assertOk()
        ->assertJsonPath('status', 'mapped');

    $this->assertDatabaseHas('core_import_jobs', [
        'id' => $job->id,
        'status' => 'mapped',
    ]);
});

// ── Execute ───────────────────────────────────────────────────────────────────

test('can execute import', function () {
     $user = actingAsUser('employee');
    Queue::fake();

    $job = ImportJob::create([
        'user_id'       => $user->id,
        'filename'      => 'test.csv',
        'file_path'     => 'imports/test.csv',
        'file_type'     => 'csv',
        'target_entity' => 'contact',
        'status'        => 'mapped',
    ]);
        $response = $this
        ->postJson("/api/v1/import/jobs/{$job->id}/execute")
        ->assertStatus(202);

    Queue::assertPushed(\Modules\Core\Jobs\ExecuteImportJob::class);
});

// ── Import creates records ────────────────────────────────────────────────────

test('import creates correct contact records', function () {
     $user = actingAsUser('employee');

    $job = ImportJob::create([
        'user_id'       => $user->id,
        'filename'      => 'contacts.csv',
        'file_path'     => 'imports/contacts.csv',
        'file_type'     => 'csv',
        'target_entity' => 'contact',
        'status'        => 'mapped',
        'total_rows'    => 1,
    ]);

    $row = ImportRow::create([
        'import_job_id' => $job->id,
        'row_index'     => 0,
        'raw_data'      => ['first_name' => 'Alice', 'last_name' => 'Wonder', 'email' => 'alice@example.com'],
        'mapped_data'   => ['first_name' => 'Alice', 'last_name' => 'Wonder', 'email' => 'alice@example.com'],
        'status'        => 'pending',
    ]);

    $executor = app(\Modules\Core\Services\ImportExecutorService::class);
    $result   = $executor->importRow($job, $row);

    expect($result)->toBeTrue();
    $this->assertDatabaseHas('crm_contacts', ['first_name' => 'Alice', 'last_name' => 'Wonder', 'email' => 'alice@example.com']);
    $this->assertDatabaseHas('core_import_rows', ['id' => $row->id, 'status' => 'imported']);
});

test('import creates correct product records', function () {
     $user = actingAsUser('employee');

    $job = ImportJob::create([
        'user_id'       => $user->id,
        'filename'      => 'products.csv',
        'file_path'     => 'imports/products.csv',
        'file_type'     => 'csv',
        'target_entity' => 'product',
        'status'        => 'mapped',
        'total_rows'    => 1,
    ]);

    $row = ImportRow::create([
        'import_job_id' => $job->id,
        'row_index'     => 0,
        'raw_data'      => ['name' => 'Test Widget', 'sku' => 'TW-001', 'price' => '19.99'],
        'mapped_data'   => ['name' => 'Test Widget', 'sku' => 'TW-001', 'price' => '19.99'],
        'status'        => 'pending',
    ]);

    $executor = app(\Modules\Core\Services\ImportExecutorService::class);
    $result   = $executor->importRow($job, $row);

    expect($result)->toBeTrue();
    $this->assertDatabaseHas('inventory_products', ['name' => 'Test Widget', 'sku' => 'TW-001']);
});

// ── Job list ──────────────────────────────────────────────────────────────────

test('index returns only user own jobs', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    ImportJob::create(['user_id' => $user1->id, 'filename' => 'a.csv', 'file_path' => 'imports/a.csv', 'file_type' => 'csv', 'target_entity' => 'contact', 'status' => 'uploaded']);
    ImportJob::create(['user_id' => $user2->id, 'filename' => 'b.csv', 'file_path' => 'imports/b.csv', 'file_type' => 'csv', 'target_entity' => 'product', 'status' => 'uploaded']);

    $this->actingAs($user1, 'sanctum')
        ->getJson('/api/v1/import/jobs')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Rows ──────────────────────────────────────────────────────────────────────

test('rows endpoint returns rows with optional status filter', function () {
     $user = actingAsUser('employee');

    $job = ImportJob::create([
        'user_id'       => $user->id,
        'filename'      => 'test.csv',
        'file_path'     => 'imports/test.csv',
        'file_type'     => 'csv',
        'target_entity' => 'contact',
        'status'        => 'completed',
    ]);

    ImportRow::create(['import_job_id' => $job->id, 'row_index' => 0, 'raw_data' => ['a' => '1'], 'status' => 'imported']);
    ImportRow::create(['import_job_id' => $job->id, 'row_index' => 1, 'raw_data' => ['a' => '2'], 'status' => 'failed', 'error_message' => 'Oops']);
        $response = $this
        ->getJson("/api/v1/import/jobs/{$job->id}/rows?status=failed")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
