<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\AlertEvent;
use Modules\BI\Models\KpiAlert;
use Modules\BI\Models\ScheduledReport;
use Modules\BI\Services\AlertService;

/**
 * @group Controllers - Kpi Alert
 *
 * Manage Kpi Alert resources.
 */
class KpiAlertController extends Controller
{
    public function __construct(private readonly AlertService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(KpiAlert::orderByDesc('created_at')->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kpi_id' => ['nullable', 'integer'],
            'metric_name' => ['required', 'string', 'max:100'],
            'condition' => ['required', 'string', 'in:above,below,equals,change_pct'],
            'threshold' => ['required', 'numeric'],
            'severity' => ['nullable', 'string', 'in:info,warning,critical'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->service->createAlert($data), 201);
    }

    public function show(KpiAlert $kpiAlert): JsonResponse
    {
        return response()->json($kpiAlert->load('events'));
    }

    public function update(Request $request, KpiAlert $kpiAlert): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'metric_name' => ['sometimes', 'string', 'max:100'],
            'condition' => ['sometimes', 'string', 'in:above,below,equals,change_pct'],
            'threshold' => ['sometimes', 'numeric'],
            'severity' => ['sometimes', 'string', 'in:info,warning,critical'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $kpiAlert->update($data);

        return response()->json($kpiAlert->fresh());
    }

    public function destroy(KpiAlert $kpiAlert): JsonResponse
    {
        $kpiAlert->delete();

        return response()->json(null, 204);
    }

    public function evaluate(Request $request, KpiAlert $kpiAlert): JsonResponse
    {
        $data = $request->validate(['value' => ['required', 'numeric']]);
        $event = $this->service->evaluateAlert($kpiAlert, (float) $data['value']);

        return response()->json(['triggered' => $event !== null, 'event' => $event]);
    }

    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['metrics' => ['required', 'array']]);
        $events = $this->service->checkAlerts($data['metrics']);

        return response()->json(['triggered_count' => $events->count(), 'events' => $events]);
    }

    public function events(KpiAlert $kpiAlert): JsonResponse
    {
        return response()->json($kpiAlert->events()->orderByDesc('created_at')->paginate(25));
    }

    public function unacknowledgedEvents(): JsonResponse
    {
        return response()->json($this->service->getUnacknowledgedEvents());
    }

    public function acknowledgeEvent(Request $request, AlertEvent $alertEvent): JsonResponse
    {
        $this->service->acknowledgeEvent($alertEvent, $request->user()->id);

        return response()->json($alertEvent->fresh());
    }

    public function indexScheduled(): JsonResponse
    {
        return response()->json(ScheduledReport::orderByDesc('created_at')->paginate(25));
    }

    public function storeScheduled(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'report_id' => ['nullable', 'integer'],
            'schedule' => ['required', 'string', 'in:daily,weekly,monthly,quarterly'],
            'format' => ['nullable', 'string', 'in:pdf,excel,csv'],
            'recipients' => ['required', 'array'],
            'recipients.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->service->createScheduledReport($data), 201);
    }

    public function showScheduled(ScheduledReport $scheduledReport): JsonResponse
    {
        return response()->json($scheduledReport);
    }

    public function updateScheduled(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'schedule' => ['sometimes', 'string', 'in:daily,weekly,monthly,quarterly'],
            'format' => ['sometimes', 'string', 'in:pdf,excel,csv'],
            'recipients' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $scheduledReport->update($data);

        return response()->json($scheduledReport->fresh());
    }

    public function destroyScheduled(ScheduledReport $scheduledReport): JsonResponse
    {
        $scheduledReport->delete();

        return response()->json(null, 204);
    }

    public function sendScheduled(ScheduledReport $scheduledReport): JsonResponse
    {
        $this->service->sendReport($scheduledReport);

        return response()->json($scheduledReport->fresh());
    }

    public function processDueReports(): JsonResponse
    {
        $sent = $this->service->processDueReports();

        return response()->json(['sent_count' => $sent->count(), 'reports' => $sent]);
    }
}
