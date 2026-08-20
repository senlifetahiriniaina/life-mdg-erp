<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\Report;
use Modules\BI\Services\ExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group BI - Report
 *
 * Generate and schedule BI reports.
 */
class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = Report::with('user')
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->latest()
            ->paginate(25);

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Chantier 19 Lot 5: the real, routed Reports/Index.vue page treats
            // `type` as an export/output format (pdf/excel/csv/dashboard —
            // confirmed via its own typeIcon()/typeOptions), not a chart type
            // — every real "Nouveau rapport" submission through the UI 422'd
            // against the old chart-type-only list. Widened to accept both
            // vocabularies rather than narrowing either, since
            // ReportFactory/BIReportingTest.php genuinely use the chart-type
            // values (table/bar/line/pie) for a different, API-level concept
            // of "report".
            'type' => ['nullable', 'in:table,bar,line,pie,area,scatter,pdf,excel,csv,dashboard'],
            'query_config' => ['nullable', 'array'],
            'chart_config' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'is_scheduled' => ['nullable', 'boolean'],
            'schedule' => ['nullable', 'string'],
            'schedule_recipients' => ['nullable', 'array'],
        ]);

        $report = Report::create(array_merge($validated, ['user_id' => $request->user()->id]));

        return response()->json($report->load('user'), 201);
    }

    public function show(Report $report): JsonResponse
    {
        return response()->json($report->load('user'));
    }

    public function update(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Chantier 19 Lot 5: the real, routed Reports/Index.vue page treats
            // `type` as an export/output format (pdf/excel/csv/dashboard —
            // confirmed via its own typeIcon()/typeOptions), not a chart type
            // — every real "Nouveau rapport" submission through the UI 422'd
            // against the old chart-type-only list. Widened to accept both
            // vocabularies rather than narrowing either, since
            // ReportFactory/BIReportingTest.php genuinely use the chart-type
            // values (table/bar/line/pie) for a different, API-level concept
            // of "report".
            'type' => ['nullable', 'in:table,bar,line,pie,area,scatter,pdf,excel,csv,dashboard'],
            'query_config' => ['nullable', 'array'],
            'chart_config' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'is_scheduled' => ['nullable', 'boolean'],
            'schedule' => ['nullable', 'string'],
            'schedule_recipients' => ['nullable', 'array'],
        ]);

        $report->update($validated);

        return response()->json($report->fresh('user'));
    }

    public function destroy(Report $report): JsonResponse
    {
        $report->delete();

        return response()->json(null, 204);
    }

    public function run(Report $report): JsonResponse
    {
        $result = $this->computeResult($report);

        return response()->json([
            'data' => $result['data']->all(),
            'columns' => $result['columns'],
            'ran_at' => now(),
        ]);
    }

    /**
     * Chantier 19 Lot 5: the real, routed Reports/Index.vue "Générer
     * maintenant" button POSTs to `bi/reports/{report}/generate` — a route
     * that has never existed (only `/run`, which no Vue page anywhere
     * actually calls) — every click 404'd. Alias `generate` onto the same
     * real `run()` behavior rather than duplicating it.
     */
    public function generate(Report $report): JsonResponse
    {
        return $this->run($report);
    }

    /**
     * Chantier 19 Lot 5: the real, routed Reports/Index.vue "Exporter
     * PDF"/"Exporter Excel" buttons GET `bi/reports/{report}/export` — a
     * route that has never existed at all, confirmed via a real HTTP 404 —
     * every click failed silently (the frontend's own catch block only
     * showed a generic toast). Wired to the same, already-real
     * ExportService pipeline the module's other export endpoints
     * (Dashboard/Widget/Query) already use.
     */
    public function export(Request $request, Report $report): BinaryFileResponse|Response
    {
        $format = $request->query('format', 'csv');
        $result = $this->computeResult($report);
        $filename = 'report_'.$report->id.'_'.now()->format('Ymd_His');

        /** @var ExportService $export */
        $export = app(ExportService::class);

        return match ($format) {
            'excel', 'xlsx' => $export->toXlsx($result['data'], $result['columns'], $filename),
            'pdf' => $export->toPdf('bi::exports.report', [
                'report' => $report,
                'rows' => $result['data'],
                'headings' => $result['columns'],
            ], $filename),
            default => $export->toCsv($result['data'], $result['columns'], $filename),
        };
    }

    /**
     * Report execution has no query-execution engine wired to arbitrary
     * `query_config` (same documented limitation this method already
     * carried before this chantier, under its old name `run()`) — this
     * honestly returns an empty result set rather than guessing at how to
     * interpret a report's stored config, matching the fallback-first
     * design principle used throughout this app. `last_run_at` is still
     * genuinely persisted for real.
     *
     * @return array{data: \Illuminate\Support\Collection<int, mixed>, columns: list<string>}
     */
    private function computeResult(Report $report): array
    {
        $report->update(['last_run_at' => now()]);

        return ['data' => collect(), 'columns' => []];
    }
}
