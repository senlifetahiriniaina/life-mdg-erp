<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\Report;

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
            'type' => ['nullable', 'in:table,bar,line,pie,area,scatter'],
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
            'type' => ['nullable', 'in:table,bar,line,pie,area,scatter'],
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
        $report->update(['last_run_at' => now()]);

        return response()->json([
            'data' => [],
            'columns' => [],
            'ran_at' => now(),
        ]);
    }
}
