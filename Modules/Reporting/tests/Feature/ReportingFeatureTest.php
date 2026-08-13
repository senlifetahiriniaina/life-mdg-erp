<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Models\ReportSchedule;
use Modules\Reporting\Services\ReportingService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function reportingUser(): User
{
    return actingAsUser('admin');
}

function createReport(array $overrides = []): ReportDefinition
{
    return ReportDefinition::create(array_merge([
        'tenant_id'      => null,
        'name'           => 'Test Report ' . uniqid(),
        'slug'           => 'test-report-' . uniqid(),
        'module'         => 'Sales',
        'description'    => 'A feature test report',
        'query_template' => 'SELECT 1 AS value',
        'output_format'  => 'table',
        'is_system'      => false,
        'is_active'      => true,
    ], $overrides));
}

// ─── Authentication ────────────────────────────────────────────────────────────

test('unauthenticated request to reporting returns 401', function () {
    $this->getJson('/api/v1/reporting/reports')
        ->assertUnauthorized();
});

// ─── API Endpoints ─────────────────────────────────────────────────────────────

test('authenticated user can list reports', function () {
    reportingUser();

    $this->getJson('/api/v1/reporting/reports')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('can list schedules via API', function () {
    reportingUser();

    $this->getJson('/api/v1/reporting/schedules')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

// ─── ReportDefinition Model ────────────────────────────────────────────────────

test('report definition isExportable returns true for pdf format', function () {
    reportingUser();
    $report = createReport(['output_format' => 'pdf']);

    expect($report->isExportable())->toBeTrue();
});

test('report definition isExportable returns true for excel format', function () {
    reportingUser();
    $report = createReport(['output_format' => 'excel']);

    expect($report->isExportable())->toBeTrue();
});

test('report definition isExportable returns false for table format', function () {
    reportingUser();
    $report = createReport(['output_format' => 'table']);

    expect($report->isExportable())->toBeFalse();
});

test('inactive report is not listed in available reports', function () {
    reportingUser();
    $inactive = createReport(['is_active' => false]);
    $active   = createReport(['is_active' => true]);

    $service = app(ReportingService::class);
    $reports  = $service->getAvailableReports();

    $ids = $reports->pluck('id');
    expect($ids->contains($active->id))->toBeTrue()
        ->and($ids->contains($inactive->id))->toBeFalse();
});

// ─── ReportingService ──────────────────────────────────────────────────────────

test('can execute a report and get completed status', function () {
    $user    = reportingUser();
    $report  = createReport(['query_template' => 'SELECT 42 AS answer']);
    $service = app(ReportingService::class);

    $execution = $service->execute($report, [], $user);

    expect($execution->status)->toBe('completed')
        ->and($execution->executed_by)->toBe($user->id);
});

test('execution is stored in the database', function () {
    $user    = reportingUser();
    $report  = createReport(['query_template' => 'SELECT 1 AS n']);
    $service = app(ReportingService::class);

    $execution = $service->execute($report, [], $user);

    test()->assertDatabaseHas('report_executions', [
        'id'                   => $execution->id,
        'report_definition_id' => $report->id,
        'status'               => 'completed',
    ]);
});

test('can filter reports by module', function () {
    reportingUser();
    createReport(['module' => 'HR', 'slug' => 'hr-' . uniqid()]);
    createReport(['module' => 'Sales', 'slug' => 'sales-' . uniqid()]);

    $service = app(ReportingService::class);

    $hrReports    = $service->getAvailableReports('HR');
    $salesReports = $service->getAvailableReports('Sales');

    expect($hrReports->every(fn ($r) => $r->module === 'HR'))->toBeTrue()
        ->and($salesReports->every(fn ($r) => $r->module === 'Sales'))->toBeTrue();
});

// ─── ReportSchedule ────────────────────────────────────────────────────────────

test('can create a report schedule', function () {
    $user    = reportingUser();
    $report  = createReport();
    $service = app(ReportingService::class);

    $schedule = $service->schedule($report, [
        'frequency'    => 'daily',
        'scheduled_at' => '08:00',
        'output_format'=> 'pdf',
        'created_by'   => $user->id,
    ]);

    expect($schedule)->toBeInstanceOf(ReportSchedule::class)
        ->and($schedule->frequency)->toBe('daily')
        ->and($schedule->is_active)->toBeTrue();
});

test('schedule computeNextRunAt advances by one day for daily frequency', function () {
    reportingUser();
    $report = createReport();

    $schedule = ReportSchedule::create([
        'report_definition_id' => $report->id,
        'frequency'            => 'daily',
        'scheduled_at'         => '06:00',
        'output_format'        => 'table',
        'is_active'            => true,
        'next_run_at'          => now(),
        'created_by'           => 1,
    ]);

    $before  = $schedule->next_run_at->copy();
    $schedule->computeNextRunAt();
    $schedule->save();

    expect($schedule->fresh()->next_run_at->greaterThan($before))->toBeTrue();
});

// ─── getDurationSeconds ────────────────────────────────────────────────────────

test('getDurationSeconds returns positive number for completed execution', function () {
    reportingUser();
    $report  = createReport(['query_template' => 'SELECT 1']);
    $service = app(ReportingService::class);
    $user    = User::first();

    $execution = $service->execute($report, [], $user);

    expect($execution->getDurationSeconds())->toBeGreaterThanOrEqual(0);
});

test('getDurationSeconds returns null when started_at is null', function () {
    reportingUser();
    $report = createReport();

    $execution = ReportExecution::create([
        'report_definition_id' => $report->id,
        'executed_by'          => 1,
        'status'               => 'pending',
        'started_at'           => null,
    ]);

    expect($execution->getDurationSeconds())->toBeNull();
});
