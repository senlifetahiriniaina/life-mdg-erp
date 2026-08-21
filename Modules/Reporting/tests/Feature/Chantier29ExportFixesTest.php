<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Services\ReportingService;

uses(RefreshDatabase::class);

/**
 * Chantier 29 — GET /reporting/executions/{id}/download used to delegate to
 * two fakes on ReportingService: generatePdf() (a plain-text file saved
 * under a .pdf name) and generateExcel() (a CSV saved under a .csv name
 * regardless of the requested "excel" format). Neither ever produced a real
 * PDF or a real XLSX file, despite a fully-built, already-injected
 * DomPDF/PhpSpreadsheet engine (ReportGenerationService::exportPdf()/
 * exportXlsx()) sitting unused in the same controller. This locks in the
 * fix: downloadExecution() now delegates to the real engine, and both
 * exports come back as genuinely valid files (magic-byte checked), not just
 * HTTP 200.
 */

/**
 * Mirrors ReportFullApiTest.php's makeReportingUser() — a real Company row
 * so users.company_id (the real multi-tenant boundary column) satisfies its
 * FK to companies.id.
 */
function chantier29ReportingUser(string $role = 'admin'): User
{
    $user    = actingAsUser($role);
    $company = \App\Models\Company::factory()->create();
    $user->update(['company_id' => $company->id]);

    return $user->fresh();
}

function chantier29Report(int $tenantId, array $overrides = []): ReportDefinition
{
    return ReportDefinition::create(array_merge([
        'tenant_id'      => $tenantId,
        'name'           => 'Chantier29 Export Report',
        'slug'           => 'chantier29-export-' . uniqid(),
        'module'         => 'Sales',
        'description'    => 'Report used to lock in real PDF/XLSX export',
        'query_template' => "SELECT 1 AS n, 'Alpha' AS label UNION ALL SELECT 2, 'Beta'",
        'output_format'  => 'table',
        'report_type'    => 'table',
        'is_system'      => false,
        'is_active'      => true,
    ], $overrides));
}

// ─── Real PDF export ────────────────────────────────────────────────────────

test('downloading a completed execution as PDF returns real PDF bytes', function () {
    $user      = chantier29ReportingUser();
    $report    = chantier29Report($user->company_id);
    $service   = app(ReportingService::class);
    $execution = $service->execute($report, [], $user);

    expect($execution->status)->toBe('completed')
        ->and($execution->result_data)->toHaveCount(2);

    $response = $this->get("/api/v1/reporting/executions/{$execution->id}/download?format=pdf");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    $bytes = $response->streamedContent();
    expect(substr($bytes, 0, 4))->toBe('%PDF');
    expect(strlen($bytes))->toBeGreaterThan(100);
});

// ─── Real XLSX export ───────────────────────────────────────────────────────

test('downloading a completed execution as Excel returns a real XLSX file, not a CSV', function () {
    $user      = chantier29ReportingUser();
    $report    = chantier29Report($user->company_id);
    $service   = app(ReportingService::class);
    $execution = $service->execute($report, [], $user);

    $response = $this->get("/api/v1/reporting/executions/{$execution->id}/download?format=excel");

    $response->assertOk();
    $response->assertHeader(
        'Content-Type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $bytes = $response->streamedContent();
    // A real .xlsx is a ZIP container — "PK\x03\x04" is the ZIP local file
    // header signature. A CSV (the old fake) would never start this way.
    expect(bin2hex(substr($bytes, 0, 4)))->toBe('504b0304');

    // Confirm it's genuinely readable as a spreadsheet, not just
    // ZIP-shaped bytes — write it to a temp file and load it back via
    // PhpSpreadsheet's own reader.
    $tmp = tempnam(sys_get_temp_dir(), 'chantier29_xlsx_');
    file_put_contents($tmp, $bytes);
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
    $rows        = $spreadsheet->getActiveSheet()->toArray();
    @unlink($tmp);

    // Loose comparison: DB::select()'s driver-returned scalar types (int vs
    // numeric-string) aren't the point here — round-tripping through a real
    // .xlsx file and reading the real rows/values back is.
    expect($rows[0])->toBe(['n', 'label']);
    expect($rows[1])->toEqual([1, 'Alpha']);
    expect($rows[2])->toEqual([2, 'Beta']);
});

test('excel is the default format when none is specified', function () {
    $user      = chantier29ReportingUser();
    $report    = chantier29Report($user->company_id);
    $service   = app(ReportingService::class);
    $execution = $service->execute($report, [], $user);

    $response = $this->get("/api/v1/reporting/executions/{$execution->id}/download");

    $response->assertOk();
    $response->assertHeader(
        'Content-Type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
});

// ─── Guardrails already covered before this fix, re-confirmed unchanged ────

test('downloading a non-completed execution is rejected with 422', function () {
    $user      = chantier29ReportingUser();
    $report    = chantier29Report($user->company_id);
    $execution = ReportExecution::create([
        'tenant_id'            => $user->company_id,
        'report_definition_id' => $report->id,
        'executed_by'          => $user->id,
        'status'               => 'running',
        'started_at'           => now(),
    ]);

    $this->get("/api/v1/reporting/executions/{$execution->id}/download?format=pdf")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Execution has not completed yet.']);
});

test('downloading an execution belonging to another tenant returns 404', function () {
    chantier29ReportingUser();

    $otherReport = chantier29Report(99999);
    $execution   = ReportExecution::create([
        'tenant_id'            => 99999,
        'report_definition_id' => $otherReport->id,
        'executed_by'          => 1,
        'status'               => 'completed',
        'started_at'           => now(),
        'completed_at'         => now(),
        'result_data'          => [['n' => 1]],
    ]);

    $this->get("/api/v1/reporting/executions/{$execution->id}/download?format=pdf")
        ->assertNotFound();
});

test('unauthenticated download request returns 401', function () {
    // getJson() (not get()) so the `Accept: application/json` header makes
    // Sanctum's auth guard return 401 rather than redirect to a login route
    // — same convention as ReportFullApiTest.php's other 401 assertions.
    $this->getJson('/api/v1/reporting/executions/1/download?format=pdf')
        ->assertUnauthorized();
});
