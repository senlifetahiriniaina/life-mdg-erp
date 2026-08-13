<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Models\ReportShare;
use Modules\Reporting\Services\ReportingService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeReportingUser(string $role = 'admin'): User
{
    return actingAsUser($role);
}

function tenantReport(int $tenantId, array $overrides = []): ReportDefinition
{
    return ReportDefinition::create(array_merge([
        'tenant_id'      => $tenantId,
        'name'           => 'Tenant Report ' . uniqid(),
        'slug'           => 'tenant-report-' . uniqid(),
        'module'         => 'Sales',
        'description'    => 'Tenant-scoped report',
        'query_template' => 'SELECT 1 AS value',
        'output_format'  => 'table',
        'report_type'    => 'table',
        'is_system'      => false,
        'is_active'      => true,
    ], $overrides));
}

function globalReport(array $overrides = []): ReportDefinition
{
    return ReportDefinition::create(array_merge([
        'tenant_id'      => null,
        'name'           => 'Global Report ' . uniqid(),
        'slug'           => 'global-report-' . uniqid(),
        'module'         => 'Sales',
        'query_template' => 'SELECT 1 AS value',
        'output_format'  => 'table',
        'report_type'    => 'table',
        'is_system'      => false,
        'is_active'      => true,
    ], $overrides));
}

// ─── Auth required ────────────────────────────────────────────────────────────

test('unauthenticated GET /reporting/reports returns 401', function () {
    $this->getJson('/api/v1/reporting/reports')
        ->assertUnauthorized();
});

test('unauthenticated POST /reporting/reports returns 401', function () {
    $this->postJson('/api/v1/reporting/reports', [])
        ->assertUnauthorized();
});

test('unauthenticated GET /reporting/reports/{id} returns 401', function () {
    $this->getJson('/api/v1/reporting/reports/1')
        ->assertUnauthorized();
});

test('unauthenticated POST /reporting/reports/{id}/run returns 401', function () {
    $this->postJson('/api/v1/reporting/reports/1/run')
        ->assertUnauthorized();
});

test('unauthenticated POST /reporting/reports/{id}/share returns 401', function () {
    $this->postJson('/api/v1/reporting/reports/1/share')
        ->assertUnauthorized();
});

// ─── List reports ─────────────────────────────────────────────────────────────

test('authenticated user can list reports', function () {
    makeReportingUser();

    $this->getJson('/api/v1/reporting/reports')
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'total']);
});

test('list reports filters by module', function () {
    $user = makeReportingUser();
    $tid  = $user->tenant_id ?? $user->id;

    tenantReport($tid, ['module' => 'HR', 'slug' => 'hr-' . uniqid()]);
    tenantReport($tid, ['module' => 'Sales', 'slug' => 'sales-' . uniqid()]);

    $response = $this->getJson('/api/v1/reporting/reports?module=HR')
        ->assertOk();

    collect($response->json('data'))->each(
        fn ($r) => expect($r['module'])->toBe('HR')
    );
});

test('list reports filters by report_type', function () {
    $user = makeReportingUser();
    $tid  = $user->tenant_id ?? $user->id;

    tenantReport($tid, ['report_type' => 'chart', 'slug' => 'chart-' . uniqid()]);
    tenantReport($tid, ['report_type' => 'pivot', 'slug' => 'pivot-' . uniqid()]);

    $response = $this->getJson('/api/v1/reporting/reports?report_type=chart')
        ->assertOk();

    collect($response->json('data'))->each(
        fn ($r) => expect($r['report_type'])->toBe('chart')
    );
});

test('list reports includes global (null tenant_id) reports', function () {
    makeReportingUser();
    $global = globalReport();

    $response = $this->getJson('/api/v1/reporting/reports')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->contains($global->id))->toBeTrue();
});

// ─── Create report ────────────────────────────────────────────────────────────

test('can create a report definition via POST /reporting/reports', function () {
    makeReportingUser();

    $this->postJson('/api/v1/reporting/reports', [
        'name'           => 'My New Report',
        'module'         => 'Inventory',
        'description'    => 'An inventory report',
        'query_template' => 'SELECT * FROM products WHERE tenant_id = {{tenant_id}}',
        'output_format'  => 'table',
        'report_type'    => 'table',
    ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'My New Report', 'module' => 'Inventory']);
});

test('create report validation fails without required fields', function () {
    makeReportingUser();

    $this->postJson('/api/v1/reporting/reports', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'module', 'query_template']);
});

test('create report rejects invalid report_type', function () {
    makeReportingUser();

    $this->postJson('/api/v1/reporting/reports', [
        'name'           => 'Bad Type',
        'module'         => 'Sales',
        'query_template' => 'SELECT 1',
        'report_type'    => 'invalid_type',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['report_type']);
});

test('created report is stored in database', function () {
    $user = makeReportingUser();

    $response = $this->postJson('/api/v1/reporting/reports', [
        'name'           => 'DB Check Report',
        'module'         => 'CRM',
        'query_template' => 'SELECT 1',
        'report_type'    => 'chart',
    ])->assertCreated();

    $this->assertDatabaseHas('report_definitions', [
        'id'     => $response->json('id'),
        'name'   => 'DB Check Report',
        'module' => 'CRM',
    ]);
});

// ─── Show report ──────────────────────────────────────────────────────────────

test('can show a report definition by ID', function () {
    $user   = makeReportingUser();
    $tid    = $user->tenant_id ?? $user->id;
    $report = tenantReport($tid);

    $this->getJson("/api/v1/reporting/reports/{$report->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $report->id]);
});

test('show returns 404 for non-existent report', function () {
    makeReportingUser();

    $this->getJson('/api/v1/reporting/reports/99999')
        ->assertNotFound();
});

// ─── Update report ────────────────────────────────────────────────────────────

test('can update a tenant-owned report via PUT', function () {
    $user   = makeReportingUser();
    $tid    = $user->tenant_id ?? $user->id;
    $report = tenantReport($tid);

    $this->putJson("/api/v1/reporting/reports/{$report->id}", [
        'name'        => 'Updated Name',
        'description' => 'Updated description',
    ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Updated Name']);

    $this->assertDatabaseHas('report_definitions', [
        'id'   => $report->id,
        'name' => 'Updated Name',
    ]);
});

test('cannot update a report belonging to another tenant', function () {
    makeReportingUser();

    // Report for another tenant (tenant_id = 99999)
    $otherReport = tenantReport(99999);

    $this->putJson("/api/v1/reporting/reports/{$otherReport->id}", [
        'name' => 'Hacked Name',
    ])
        ->assertNotFound();
});

// ─── Delete report ────────────────────────────────────────────────────────────

test('can delete a tenant-owned report', function () {
    $user   = makeReportingUser();
    $tid    = $user->tenant_id ?? $user->id;
    $report = tenantReport($tid);

    $this->deleteJson("/api/v1/reporting/reports/{$report->id}")
        ->assertOk()
        ->assertJsonFragment(['message' => 'Report deleted.']);

    $this->assertDatabaseMissing('report_definitions', ['id' => $report->id]);
});

test('cannot delete a system report', function () {
    $user   = makeReportingUser();
    $tid    = $user->tenant_id ?? $user->id;
    $report = tenantReport($tid, ['is_system' => true]);

    $this->deleteJson("/api/v1/reporting/reports/{$report->id}")
        ->assertUnprocessable();

    $this->assertDatabaseHas('report_definitions', ['id' => $report->id]);
});

test('cannot delete a report belonging to another tenant', function () {
    makeReportingUser();

    $otherReport = tenantReport(99999);

    $this->deleteJson("/api/v1/reporting/reports/{$otherReport->id}")
        ->assertNotFound();
});

// ─── Run report by ID ─────────────────────────────────────────────────────────

test('POST /reporting/reports/{id}/run creates a ReportExecution', function () {
    $user   = makeReportingUser();
    $tid    = $user->tenant_id ?? $user->id;
    $report = tenantReport($tid, ['query_template' => 'SELECT 42 AS val']);

    $response = $this->postJson("/api/v1/reporting/reports/{$report->id}/run")
        ->assertCreated()
        ->assertJsonStructure(['execution_id', 'status', 'execution']);

    $this->assertDatabaseHas('report_executions', [
        'id'                   => $response->json('execution_id'),
        'report_definition_id' => $report->id,
    ]);
});

test('run report returns completed status for valid query', function () {
    $user   = makeReportingUser();
    $tid    = $user->tenant_id ?? $user->id;
    $report = tenantReport($tid, ['query_template' => 'SELECT 1 AS n']);

    $this->postJson("/api/v1/reporting/reports/{$report->id}/run")
        ->assertCreated()
        ->assertJsonPath('status', 'completed');
});

test('cannot run a report from another tenant', function () {
    makeReportingUser();
    $otherReport = tenantReport(99999);

    $this->postJson("/api/v1/reporting/reports/{$otherReport->id}/run")
        ->assertNotFound();
});

// ─── Execution history ────────────────────────────────────────────────────────

test('GET /reporting/reports/{id}/executions lists executions for a report', function () {
    $user    = makeReportingUser();
    $tid     = $user->tenant_id ?? $user->id;
    $report  = tenantReport($tid, ['query_template' => 'SELECT 1']);
    $service = app(ReportingService::class);

    $service->execute($report, [], $user);
    $service->execute($report, [], $user);

    $response = $this->getJson("/api/v1/reporting/reports/{$report->id}/executions")
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);

    expect($response->json('total'))->toBeGreaterThanOrEqual(2);
});

test('execution history is tenant-isolated', function () {
    $user    = makeReportingUser();
    $tid     = $user->tenant_id ?? $user->id;
    $report  = tenantReport($tid, ['query_template' => 'SELECT 1']);
    $service = app(ReportingService::class);
    $service->execute($report, [], $user);

    // Other tenant's report
    $otherReport = tenantReport(99999);

    $this->getJson("/api/v1/reporting/reports/{$otherReport->id}/executions")
        ->assertNotFound();
});

// ─── Show execution ───────────────────────────────────────────────────────────

test('GET /reporting/executions/{id} returns execution with definition', function () {
    $user    = makeReportingUser();
    $tid     = $user->tenant_id ?? $user->id;
    $report  = tenantReport($tid, ['query_template' => 'SELECT 5 AS x']);
    $service = app(ReportingService::class);

    $execution = $service->execute($report, [], $user);

    $this->getJson("/api/v1/reporting/executions/{$execution->id}")
        ->assertOk()
        ->assertJsonStructure(['id', 'status', 'definition']);
});

test('cannot access execution belonging to another tenant', function () {
    $user    = makeReportingUser();
    $tid     = $user->tenant_id ?? $user->id;

    // Create execution for another tenant manually
    $report = tenantReport(99999, ['query_template' => 'SELECT 1']);
    $execution = ReportExecution::create([
        'tenant_id'            => 99999,
        'report_definition_id' => $report->id,
        'executed_by'          => $user->id,
        'status'               => 'completed',
        'started_at'           => now(),
        'completed_at'         => now(),
    ]);

    $this->getJson("/api/v1/reporting/executions/{$execution->id}")
        ->assertNotFound();
});

// ─── Share report ─────────────────────────────────────────────────────────────

test('can share a report with another user', function () {
    $owner  = makeReportingUser();
    $tid    = $owner->tenant_id ?? $owner->id;
    $report = tenantReport($tid);

    // Create a second user to share with
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $recipient = User::factory()->create();

    $this->postJson("/api/v1/reporting/reports/{$report->id}/share", [
        'shared_with_user_id' => $recipient->id,
        'permission'          => 'view',
    ])
        ->assertCreated()
        ->assertJsonStructure(['id', 'report_id', 'shared_with_user_id', 'permission']);

    $this->assertDatabaseHas('report_shares', [
        'report_id'           => $report->id,
        'shared_with_user_id' => $recipient->id,
        'permission'          => 'view',
    ]);
});

test('share report with edit permission is stored correctly', function () {
    $owner  = makeReportingUser();
    $tid    = $owner->tenant_id ?? $owner->id;
    $report = tenantReport($tid);

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $recipient = User::factory()->create();

    $this->postJson("/api/v1/reporting/reports/{$report->id}/share", [
        'shared_with_user_id' => $recipient->id,
        'permission'          => 'edit',
    ])
        ->assertCreated()
        ->assertJsonPath('permission', 'edit');
});

test('share requires valid user id', function () {
    $user   = makeReportingUser();
    $tid    = $user->tenant_id ?? $user->id;
    $report = tenantReport($tid);

    $this->postJson("/api/v1/reporting/reports/{$report->id}/share", [
        'shared_with_user_id' => 999999,
        'permission'          => 'view',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shared_with_user_id']);
});

test('cannot share a report belonging to another tenant', function () {
    makeReportingUser();
    $otherReport = tenantReport(99999);

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $recipient = User::factory()->create();

    $this->postJson("/api/v1/reporting/reports/{$otherReport->id}/share", [
        'shared_with_user_id' => $recipient->id,
        'permission'          => 'view',
    ])
        ->assertNotFound();
});

// ─── ReportShare model ────────────────────────────────────────────────────────

test('ReportShare canEdit returns true for edit permission', function () {
    makeReportingUser();
    $report = globalReport();

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $recipient = User::factory()->create();

    $share = ReportShare::create([
        'report_id'           => $report->id,
        'shared_with_user_id' => $recipient->id,
        'permission'          => 'edit',
    ]);

    expect($share->canEdit())->toBeTrue();
});

test('ReportShare canEdit returns false for view permission', function () {
    makeReportingUser();
    $report = globalReport();

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $recipient = User::factory()->create();

    $share = ReportShare::create([
        'report_id'           => $report->id,
        'shared_with_user_id' => $recipient->id,
        'permission'          => 'view',
    ]);

    expect($share->canEdit())->toBeFalse();
});

// ─── Tenant isolation ─────────────────────────────────────────────────────────

test('tenant A cannot see tenant B reports in listing', function () {
    $user = makeReportingUser();
    $tid  = $user->tenant_id ?? $user->id;

    // Tenant A's own report
    $myReport    = tenantReport($tid, ['slug' => 'mine-' . uniqid()]);
    // Tenant B's private report (tenant_id 99999, not null → not global)
    $otherReport = tenantReport(99999, ['slug' => 'theirs-' . uniqid()]);

    $response = $this->getJson('/api/v1/reporting/reports')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->contains($myReport->id))->toBeTrue()
        ->and($ids->contains($otherReport->id))->toBeFalse();
});

test('forTenant scope excludes global null-tenant reports', function () {
    $user = makeReportingUser();
    $tid  = $user->tenant_id ?? $user->id;

    $globalRep  = globalReport();
    $tenantRep  = tenantReport($tid);

    $tenantOnly = ReportDefinition::forTenant($tid)->pluck('id');
    expect($tenantOnly->contains($tenantRep->id))->toBeTrue()
        ->and($tenantOnly->contains($globalRep->id))->toBeFalse();
});

// ─── Schedules ────────────────────────────────────────────────────────────────

test('can list schedules as authenticated user', function () {
    makeReportingUser();

    $this->getJson('/api/v1/reporting/schedules')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('can create a schedule via POST /reporting/schedules', function () {
    $user   = makeReportingUser();
    $report = globalReport();

    $this->postJson('/api/v1/reporting/schedules', [
        'report_definition_id' => $report->id,
        'name'                 => 'Weekly Sales',
        'frequency'            => 'weekly',
        'recipients'           => ['manager@example.com'],
    ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Weekly Sales', 'frequency' => 'weekly']);
});
