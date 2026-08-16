<?php

declare(strict_types=1);

use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Models\ReportSchedule;
use Modules\Reporting\Services\ReportingService;

beforeEach(function () {
    $this->user     = actingAsUser('admin');
    $this->service  = app(ReportingService::class);
    $this->tenantId = $this->user->tenant_id ?? 1;
});

// ─── Helper ────────────────────────────────────────────────────────────────────

function makeReportDefinition(array $override = []): ReportDefinition
{
    return ReportDefinition::create(array_merge([
        'tenant_id'      => null, // global
        'name'           => 'Test Report',
        'slug'           => 'test-report-' . uniqid(),
        'module'         => 'Sales',
        'description'    => 'A test report',
        'query_template' => 'SELECT 1 AS value WHERE 1 = 1',
        'output_format'  => 'table',
        'is_system'      => false,
        'is_active'      => true,
    ], $override));
}

// ─── Tests ─────────────────────────────────────────────────────────────────────

test('test_can_list_available_reports', function () {
    $report = makeReportDefinition();

    $reports = $this->service->getAvailableReports();

    expect($reports)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($reports->contains('id', $report->id))->toBeTrue();
});

test('test_can_filter_available_reports_by_module', function () {
    makeReportDefinition(['slug' => 'sales-test-' . uniqid(), 'module' => 'Sales']);
    makeReportDefinition(['slug' => 'hr-test-' . uniqid(), 'module' => 'HR']);

    $salesReports = $this->service->getAvailableReports('Sales');
    $hrReports    = $this->service->getAvailableReports('HR');

    expect($salesReports->every(fn ($r) => $r->module === 'Sales'))->toBeTrue()
        ->and($hrReports->every(fn ($r) => $r->module === 'HR'))->toBeTrue();
});

test('test_can_execute_report', function () {
    $report = makeReportDefinition([
        'query_template' => 'SELECT 1 AS result',
    ]);

    $execution = $this->service->execute($report, [], $this->user);

    expect($execution)->toBeInstanceOf(ReportExecution::class)
        ->and($execution->status)->toBe('completed')
        ->and($execution->result_count)->toBeGreaterThanOrEqual(0);
});

test('test_report_execution_stored_in_database', function () {
    $report = makeReportDefinition([
        'query_template' => 'SELECT 42 AS answer',
    ]);

    $execution = $this->service->execute($report, [], $this->user);

    $this->assertDatabaseHas('report_executions', [
        'id'                    => $execution->id,
        'report_definition_id'  => $report->id,
        'executed_by'           => $this->user->id,
        'status'                => 'completed',
    ]);

    expect($execution->started_at)->not->toBeNull()
        ->and($execution->completed_at)->not->toBeNull();
});

test('test_failed_execution_is_recorded_correctly', function () {
    $report = makeReportDefinition([
        'query_template' => 'SELECT * FROM this_table_does_not_exist_at_all',
    ]);

    $execution = $this->service->execute($report, [], $this->user);

    expect($execution->status)->toBe('failed')
        ->and($execution->error_message)->not->toBeNull();

    $this->assertDatabaseHas('report_executions', ['id' => $execution->id, 'status' => 'failed']);
});

test('test_can_schedule_report', function () {
    $report = makeReportDefinition();

    $schedule = ReportSchedule::create([
        'tenant_id'            => $this->tenantId,
        'report_definition_id' => $report->id,
        'name'                 => 'Weekly Sales',
        'frequency'            => 'weekly',
        'recipients'           => ['cfo@example.com', 'coo@example.com'],
        'is_active'            => true,
    ]);

    expect($schedule)->toBeInstanceOf(ReportSchedule::class)
        ->and($schedule->frequency)->toBe('weekly')
        ->and($schedule->recipients)->toHaveCount(2);

    $this->assertDatabaseHas('report_schedules', [
        'id'        => $schedule->id,
        'frequency' => 'weekly',
        'is_active' => true,
    ]);
});

test('test_unauthorized_cannot_execute_reports', function () {
    \Illuminate\Support\Facades\Auth::forgetGuards();

    $report = makeReportDefinition(['slug' => 'protected-report-' . uniqid()]);

    // Unauthenticated request
    $response = $this->postJson("/api/v1/reporting/reports/{$report->slug}/execute");

    $response->assertStatus(401);
});

test('test_api_can_list_reports', function () {
    makeReportDefinition(['slug' => 'api-list-' . uniqid()]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reporting/reports');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('test_api_can_create_schedule', function () {
    $report = makeReportDefinition(['slug' => 'schedule-api-' . uniqid()]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/reporting/schedules', [
            'report_definition_id' => $report->id,
            'name'                 => 'Daily Digest',
            'frequency'            => 'daily',
            'recipients'           => ['manager@example.com'],
        ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['name' => 'Daily Digest', 'frequency' => 'daily']);
});

test('test_schedule_next_run_computed_correctly', function () {
    $report   = makeReportDefinition();
    $schedule = ReportSchedule::create([
        'tenant_id'            => $this->tenantId,
        'report_definition_id' => $report->id,
        'name'                 => 'Monthly',
        'frequency'            => 'monthly',
        'recipients'           => ['user@example.com'],
        'is_active'            => true,
    ]);

    $next = $schedule->computeNextRunAt();

    // Next run should be ~1 month in the future from now
    expect($next->isAfter(now()))->toBeTrue()
        ->and($next->diffInDays(now(), true))->toBeGreaterThanOrEqual(28);
});

// ─── Additional Tests ──────────────────────────────────────────────────────────

test('execute report with {{tenant_id}} auto-injected', function () {
    // Use a query template that references {{tenant_id}} — it should be replaced with the user's tenant
    $report = makeReportDefinition([
        'query_template' => 'SELECT {{tenant_id}} AS tenant',
    ]);

    $execution = $this->service->execute($report, [], $this->user);

    expect($execution->status)->toBe('completed')
        ->and($execution->result_count)->toBeGreaterThanOrEqual(1);
});

test('execute report with custom parameter', function () {
    $report = makeReportDefinition([
        'query_template' => 'SELECT {{tenant_id}} AS tenant, {{limit_val}} AS lim',
    ]);

    $execution = $this->service->execute($report, ['limit_val' => 10], $this->user);

    expect($execution->status)->toBe('completed');
    // The result_data should contain the bound value
    expect($execution->result_data)->not->toBeNull();
});

test('failed execution records error_message', function () {
    $report = makeReportDefinition([
        'query_template' => 'SELECT * FROM absolutely_nonexistent_table_xyz',
    ]);

    $execution = $this->service->execute($report, [], $this->user);

    expect($execution->status)->toBe('failed')
        ->and($execution->error_message)->not->toBeEmpty();

    $this->assertDatabaseHas('report_executions', [
        'id'     => $execution->id,
        'status' => 'failed',
    ]);
});

test('getDurationSeconds returns positive value on completed execution', function () {
    $report    = makeReportDefinition(['query_template' => 'SELECT 1']);
    $execution = $this->service->execute($report, [], $this->user);

    expect($execution->status)->toBe('completed');
    $duration = $execution->getDurationSeconds();
    expect($duration)->not->toBeNull()
        ->and($duration)->toBeGreaterThanOrEqual(0.0);
});

test('getDurationSeconds returns null when started_at is null', function () {
    $report    = makeReportDefinition();
    $execution = ReportExecution::create([
        'tenant_id'            => $this->tenantId,
        'report_definition_id' => $report->id,
        'executed_by'          => $this->user->id,
        'parameters'           => [],
        'status'               => 'pending',
        'started_at'           => null,
        'completed_at'         => null,
    ]);

    expect($execution->getDurationSeconds())->toBeNull();
});

test('schedule computeNextRunAt for daily frequency adds one day', function () {
    $report   = makeReportDefinition();
    $schedule = ReportSchedule::create([
        'tenant_id'            => $this->tenantId,
        'report_definition_id' => $report->id,
        'name'                 => 'Daily Test',
        'frequency'            => 'daily',
        'recipients'           => ['user@example.com'],
        'is_active'            => true,
    ]);

    $next = $schedule->computeNextRunAt();

    expect($next->diffInHours(now(), true))->toBeGreaterThanOrEqual(23)
        ->and($next->diffInHours(now(), true))->toBeLessThanOrEqual(25);
});

test('schedule computeNextRunAt for weekly frequency adds one week', function () {
    $report   = makeReportDefinition();
    $schedule = ReportSchedule::create([
        'tenant_id'            => $this->tenantId,
        'report_definition_id' => $report->id,
        'name'                 => 'Weekly Test',
        'frequency'            => 'weekly',
        'recipients'           => ['user@example.com'],
        'is_active'            => true,
    ]);

    $next = $schedule->computeNextRunAt();

    expect($next->diffInDays(now(), true))->toBeGreaterThanOrEqual(6)
        ->and($next->diffInDays(now(), true))->toBeLessThanOrEqual(8);
});

test('schedule computeNextRunAt for monthly frequency adds one month', function () {
    $report   = makeReportDefinition();
    $schedule = ReportSchedule::create([
        'tenant_id'            => $this->tenantId,
        'report_definition_id' => $report->id,
        'name'                 => 'Monthly Test 2',
        'frequency'            => 'monthly',
        'recipients'           => ['cfo@example.com'],
        'is_active'            => true,
    ]);

    $next = $schedule->computeNextRunAt();

    expect($next->isAfter(now()))->toBeTrue()
        ->and($next->diffInDays(now(), true))->toBeGreaterThanOrEqual(28);
});

test('report execution status transitions from pending to completed', function () {
    $report    = makeReportDefinition(['query_template' => 'SELECT 99 AS val']);
    $execution = $this->service->execute($report, [], $this->user);

    // After execute() the record should be 'completed', not pending or running
    expect($execution->status)->toBe('completed')
        ->and($execution->started_at)->not->toBeNull()
        ->and($execution->completed_at)->not->toBeNull();
});

test('list executions for a specific report', function () {
    $report = makeReportDefinition(['query_template' => 'SELECT 1']);

    // Run 3 executions
    $this->service->execute($report, [], $this->user);
    $this->service->execute($report, [], $this->user);
    $this->service->execute($report, [], $this->user);

    $executions = ReportExecution::where('report_definition_id', $report->id)->get();
    expect($executions)->toHaveCount(3);
});

test('global null tenant report definition visible to all tenants', function () {
    // makeReportDefinition defaults to tenant_id = null (global)
    $globalReport = makeReportDefinition([
        'slug' => 'global-visible-' . uniqid(),
        'name' => 'Global Report',
    ]);

    expect($globalReport->tenant_id)->toBeNull();

    // Should appear in available reports for any user
    $reports = $this->service->getAvailableReports();
    expect($reports->contains('id', $globalReport->id))->toBeTrue();
});
