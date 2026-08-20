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
// NOTE — this docblock previously claimed `bi_reports` was missing
// `query_config`/`chart_config`/`filters`/`schedule`/`schedule_recipients`/
// `last_run_at`, making `run()`/`store()`/`update()` guaranteed 500s. That
// was true when this comment was written but was independently fixed the
// same day by `2026_08_24_000001_patch_remaining_bi_stub_tables.php`
// (Chantier 8.2's BI stub-table patch) — this comment was simply never
// updated to match. Re-verified empirically in Chantier 19 Lot 5 via a real
// `Schema::getColumnListing('bi_reports')` call and a real
// `POST /bi/reports/{report}/run` HTTP request: all 6 columns exist and
// `run()` returns 200 with real (honestly-empty, no query-execution engine
// wired to arbitrary `query_config` — see `ReportController::computeResult()`'s
// own docblock) data. See `it('runs a report and persists last_run_at')`
// below, which now locks this in.

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

    // Chantier 19 Lot 5: the real, routed Reports/Index.vue "Nouveau rapport"
    // dialog only ever sends `type` as pdf/excel/csv/dashboard (its own
    // typeOptions/typeIcon() confirm this is an export-format concept on
    // this page, not the chart-type vocabulary the factory/test above use)
    // — but store()'s validation only accepted table/bar/line/pie/area/
    // scatter, so every real "Créer le rapport" click 422'd. Confirmed
    // empirically before the fix.
    it('creates a report with the real Reports page export-format vocabulary', function () {
        $this->postJson('/api/v1/bi/reports', [
            'name' => 'Rapport PDF',
            'type' => 'pdf',
        ])->assertCreated()->assertJsonPath('type', 'pdf');

        $this->postJson('/api/v1/bi/reports', [
            'name' => 'Rapport Excel',
            'type' => 'dashboard',
        ])->assertCreated()->assertJsonPath('type', 'dashboard');
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

    it('runs a report and persists last_run_at', function () {
        $report = Report::factory()->create(['last_run_at' => null]);

        $this->postJson("/api/v1/bi/reports/{$report->id}/run")
            ->assertOk()
            ->assertJsonStructure(['data', 'columns', 'ran_at']);

        expect($report->fresh()->last_run_at)->not->toBeNull();
    });

    // Chantier 19 Lot 5: Reports/Index.vue's "Générer maintenant" button
    // POSTs to `/generate`, a route that never existed (404 on every real
    // click) — the routed `/run` endpoint above was never what the frontend
    // actually called. New alias route, locked in here.
    it('generate is a real, reachable alias for run (the route the real UI calls)', function () {
        $report = Report::factory()->create();

        $this->postJson("/api/v1/bi/reports/{$report->id}/generate")
            ->assertOk()
            ->assertJsonStructure(['data', 'columns', 'ran_at']);
    });

    // Chantier 19 Lot 5: Reports/Index.vue's "Exporter PDF"/"Exporter Excel"
    // buttons GET `/export`, a route that never existed anywhere in this
    // module (confirmed via a real 404) — every export click failed.
    it('exports a report as CSV, XLSX and PDF via the new real /export route', function () {
        $report = Report::factory()->create();

        $this->get("/api/v1/bi/reports/{$report->id}/export?format=csv")->assertOk();
        $this->get("/api/v1/bi/reports/{$report->id}/export?format=excel")->assertOk();
        $this->get("/api/v1/bi/reports/{$report->id}/export?format=pdf")->assertOk();
    });
});

it('rejects an unauthenticated request to list reports', function () {
    $this->getJson('/api/v1/bi/reports')->assertUnauthorized();
});

// ─── Report export (ExportService) ──────────────────────────────────────────
// `Report` now has its own real `GET /bi/reports/{report}/export` route too
// (Chantier 19 Lot 5, see the describe('Reports API') block above) — kept
// here as a lower-level unit check of the shared ExportService plumbing.
describe('Report export via ExportService', function () {
    it('exports report-shaped data to CSV via the real ExportService', function () {
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
