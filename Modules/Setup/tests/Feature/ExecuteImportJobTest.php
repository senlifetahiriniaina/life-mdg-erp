<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Setup\Jobs\ExecuteImportJob;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Services\ImportExecutorService;

uses(RefreshDatabase::class);

// ============================================================
// Helpers
// ============================================================

function importJobForQueue(array $overrides = []): ImportJob
{
    return ImportJob::factory()->create(array_merge([
        'tenant_id'     => 1,
        'name'          => 'Queue Test Import',
        'source_type'   => 'csv',
        'target_module' => 'CRM',
        'target_entity' => 'contacts',
        'status'        => 'mapping',
    ], $overrides));
}

/**
 * Chantier 8.5sv: import-jobs/execute now requires
 * role:employee,admin,super-admin at the route level (previously ungated).
 */
function makeQueueTestUser(): \App\Models\User
{
    // Chantier 8.5sv: SetupController::tenantId() now reads users.tenant_id
    // (the real multi-tenant boundary column), not users.company_id.
    $company = \App\Models\Company::factory()->create();
    $u = \App\Models\User::factory()->create(['company_id' => $company->id, 'tenant_id' => $company->id]);

    // The route gate is role:employee,..., but SetupController's mutating
    // methods also now call authorize() against ImportSessionPolicy, which
    // checks real setup.import.* permissions — a bare Role::firstOrCreate()
    // assigns an empty-permission role and still 403s. Run the real seeder
    // (same pattern as tests/TestCase::actingAsUser()) so 'employee' carries
    // its real broad permission set.
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    $u->assignRole('employee');

    return $u;
}

// ============================================================
// ExecuteImportJob — unit-level behaviour
// ============================================================

test('handle sets status to analyzing then delegates to ImportExecutorService', function () {
    $job = importJobForQueue();

    $service = Mockery::mock(ImportExecutorService::class);
    $service->shouldReceive('execute')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->id === $job->id))
        ->andReturnNull();

    $queueJob = new ExecuteImportJob($job);
    $queueJob->handle($service);

    // Reload from DB — service mock didn't change status, so 'analyzing' persists
    $job->refresh();
    expect($job->status)->toBe('analyzing');
});

test('failed sets status to failed and stores error_summary', function () {
    $job = importJobForQueue(['status' => 'importing']);

    $exception = new \RuntimeException('Disk full');

    $queueJob = new ExecuteImportJob($job);
    $queueJob->failed($exception);

    $job->refresh();
    expect($job->status)->toBe('failed');
    expect($job->error_summary)->toMatchArray(['message' => 'Disk full']);
    expect($job->completed_at)->not->toBeNull();
});

test('failed stores the exception class in error_summary', function () {
    $job = importJobForQueue();
    $exception = new \InvalidArgumentException('Bad mapping');

    (new ExecuteImportJob($job))->failed($exception);

    $job->refresh();
    expect($job->error_summary['class'])->toBe(\InvalidArgumentException::class);
});

// ============================================================
// SetupController::executeJob — queue dispatch behaviour
// ============================================================

test('executeJob returns 202 and dispatches ExecuteImportJob for a mapping-status job', function () {
    Queue::fake();

    $user = makeQueueTestUser();
    $job  = importJobForQueue(['status' => 'mapping', 'tenant_id' => $user->tenant_id]);

    // Attach a confirmed mapping so the confirmed-count guard passes
    \Modules\Setup\Models\FieldMapping::factory()->create([
        'import_job_id' => $job->id,
        'is_confirmed'  => true,
        'source_field'  => 'email',
        'target_field'  => 'email',
        'target_table'  => 'crm_contacts',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/setup/import-jobs/{$job->id}/execute");

    $response->assertStatus(202)
        ->assertJsonFragment(['status' => 'queued', 'job_id' => $job->id]);

    Queue::assertPushed(ExecuteImportJob::class, fn ($queued) => $queued->importJob->id === $job->id);
});

test('executeJob returns 202 for a validating-status job', function () {
    Queue::fake();

    $user = makeQueueTestUser();
    $job  = importJobForQueue(['status' => 'validating', 'tenant_id' => $user->tenant_id]);

    \Modules\Setup\Models\FieldMapping::factory()->create([
        'import_job_id' => $job->id,
        'is_confirmed'  => true,
        'source_field'  => 'name',
        'target_field'  => 'full_name',
        'target_table'  => 'crm_contacts',
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/setup/import-jobs/{$job->id}/execute")
        ->assertStatus(202);
});

test('executeJob returns 202 for a pending-status job', function () {
    Queue::fake();

    $user = makeQueueTestUser();
    $job  = importJobForQueue(['status' => 'pending', 'tenant_id' => $user->tenant_id]);

    \Modules\Setup\Models\FieldMapping::factory()->create([
        'import_job_id' => $job->id,
        'is_confirmed'  => true,
        'source_field'  => 'name',
        'target_field'  => 'full_name',
        'target_table'  => 'crm_contacts',
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/setup/import-jobs/{$job->id}/execute")
        ->assertStatus(202);
});

test('executeJob returns 409 when job is already importing', function () {
    Queue::fake();

    $user = makeQueueTestUser();
    $job  = importJobForQueue(['status' => 'importing', 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->postJson("/api/v1/setup/import-jobs/{$job->id}/execute")
        ->assertStatus(409);

    Queue::assertNothingPushed();
});

test('executeJob returns 409 when job is already completed', function () {
    Queue::fake();

    $user = makeQueueTestUser();
    $job  = importJobForQueue(['status' => 'completed', 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->postJson("/api/v1/setup/import-jobs/{$job->id}/execute")
        ->assertStatus(409);

    Queue::assertNothingPushed();
});

test('executeJob returns 422 when no confirmed mappings exist', function () {
    Queue::fake();

    $user = makeQueueTestUser();
    $job  = importJobForQueue(['status' => 'mapping', 'tenant_id' => $user->tenant_id]);
    // No FieldMapping records created — confirmed count = 0

    $this->actingAs($user)
        ->postJson("/api/v1/setup/import-jobs/{$job->id}/execute")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'No confirmed mappings found. Map and confirm fields first.']);

    Queue::assertNothingPushed();
});

test('executeJob returns 404 for a job belonging to another tenant', function () {
    Queue::fake();

    $user = makeQueueTestUser();
    $otherJob   = importJobForQueue(['status' => 'mapping', 'tenant_id' => 99]);

    $this->actingAs($user)
        ->postJson("/api/v1/setup/import-jobs/{$otherJob->id}/execute")
        ->assertStatus(404);

    Queue::assertNothingPushed();
});
