<?php

declare(strict_types=1);

use Modules\BI\Models\Report;
use Modules\BI\Models\ScheduledReport;
use Modules\BI\Services\ExportService;

// This suite targets the BI reporting capability the ERP actually operates on:
// the `Report` model (`bi_reports` table) + `ReportController`
// (GET/POST/PUT/DELETE /api/v1/bi/reports, POST /bi/reports/{report}/run),
// `ExportService` (CSV/XLSX/PDF), and `ScheduledReport` via `KpiAlertController`
// (/api/v1/bi/scheduled-reports/*). See CLAUDE.md's "Known gaps" note on the
// orphan `Modules\BI\Services\BIService` / `Modules\BI\Models\DataSource`
// prototype this suite used to (incorrectly) target — neither class exists,
// and this file previously failed on `new BIService()` before a single
// scenario ran.
//
// NOTE — discovered while rewriting this suite (not fixed here, out of scope
// for a test-only rewrite): `Report`'s $fillable/$casts list `query_config`,
// `chart_config`, `filters`, `schedule`, `schedule_recipients` and
// `last_run_at`, but no migration anywhere actually adds those columns to
// `bi_reports` (confirmed both in the sqlite test DB and via
// `Schema::getColumnListing('bi_reports')` against the app's own configured
// DB — not a test-only artifact). `bi_reports` really only has: id,
// tenant_id, name, config, user_id, type, is_scheduled, description,
// timestamps, deleted_at. Two consequences kept out of this suite:
//   1. `ReportController::store()`/`update()` only work for the fields that
//      are real columns (name, description, type, is_scheduled) — sending
//      query_config/chart_config/filters/schedule/schedule_recipients 500s.
//   2. `ReportController::run()` unconditionally does
//      `$report->update(['last_run_at' => now()])`, which 500s every time
//      since that column doesn't exist — POST /bi/reports/{report}/run is
//      currently broken in this app, not just untested. No test below
//      exercises `run()`; a real fix belongs in a schema-patch migration
//      (see the existing precedent at
//      database/migrations/2026_06_02_000002_patch_bi_helpdesk_missing_columns.php),
//      which is a follow-up, not part of this test-rewrite task.

// ─── Reports API ────────────────────────────────────────────────────────────
describe('Reports API', function () {
    beforeEach(function () {
        actingAsUser('manager');
    });

    it('creates a report', function () {
        $response = $this->postJson('/api/v1/bi/reports', [
            'name' => 'Sales Report',
            'type' => 'table',
            'description' => 'Monthly sales summary',
        ])->assertCreated();

        $this->assertDatabaseHas('bi_reports', ['name' => 'Sales Report']);
        expect($response->json('id'))->not->toBeNull();
    });

    it('lists reports', function () {
        Report::factory(3)->create();

        $this->getJson('/api/v1/bi/reports')->assertOk()->assertJsonCount(3, 'data');
    });

    it('shows a report', function () {
        $report = Report::factory()->create();

        $this->getJson("/api/v1/bi/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('id', $report->id);
    });

    it('updates a report', function () {
        $report = Report::factory()->create(['name' => 'Old Name']);

        $this->putJson("/api/v1/bi/reports/{$report->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');
    });

    it('deletes (soft-deletes) a report', function () {
        $report = Report::factory()->create();

        $this->deleteJson("/api/v1/bi/reports/{$report->id}")->assertNoContent();
        $this->assertSoftDeleted('bi_reports', ['id' => $report->id]);
    });
});

it('rejects an unauthenticated request to list reports', function () {
    $this->getJson('/api/v1/bi/reports')->assertUnauthorized();
});

// ─── Report export (ExportService) ──────────────────────────────────────────
describe('Report export via ExportService', function () {
    it('exports report-shaped data to CSV via the real ExportService', function () {
        // `Report` itself has no dedicated export route (only Dashboards, Widgets
        // and saved Queries do via ExportController — see ExportTest.php), but the
        // same production ExportService that backs those endpoints handles report
        // result sets identically: a Collection of rows + headings in, a download
        // response out.
        $data = collect([
            ['id' => 1, 'value' => 100],
            ['id' => 2, 'value' => 200],
        ]);

        $response = app(ExportService::class)->toCsv($data, ['ID', 'Value'], 'sales_report');

        expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class);
    });
});

// ─── Scheduled report delivery (ScheduledReport / KpiAlertController) ───────
describe('Scheduled report delivery', function () {
    beforeEach(function () {
        actingAsUser('manager');
    });

    it('schedules a report for recurring delivery', function () {
        $report = Report::factory()->create();

        $response = $this->postJson('/api/v1/bi/scheduled-reports', [
            'name' => 'Weekly Sales Report',
            'report_id' => $report->id,
            'schedule' => 'weekly',
            'format' => 'pdf',
            'recipients' => ['user@example.com'],
        ])->assertCreated();

        $this->assertDatabaseHas('bi_scheduled_reports', [
            'id' => $response->json('id'),
            'report_id' => $report->id,
        ]);
    });

    it('sends a due scheduled report and increments send_count', function () {
        $scheduled = ScheduledReport::factory()->create(['send_count' => 0]);

        $this->postJson("/api/v1/bi/scheduled-reports/{$scheduled->id}/send")
            ->assertOk()
            ->assertJsonPath('send_count', 1);
    });

    it('processes all due scheduled reports', function () {
        ScheduledReport::factory()->due()->create();

        $this->postJson('/api/v1/bi/scheduled-reports/process-due')
            ->assertOk()
            ->assertJsonPath('sent_count', 1);
    });
});
