<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\CashFlowForecast;
use Modules\Accounting\Models\TreasuryAlert;
use Modules\Accounting\Services\CashFlowForecastService;

/**
 * @group Accounting - Treasury Planning
 *
 * Cash flow forecasting and treasury management.
 * Implements Upmetrics-style 30/60/90-day forward projections.
 */
class TreasuryController extends Controller
{
    public function __construct(private readonly CashFlowForecastService $service) {}

    // ─── Forecasts ────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $forecasts = CashFlowForecast::with('createdBy')
            ->latest()
            ->paginate(15);

        return response()->json($forecasts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_date' => 'required|date',
            'horizon' => 'required|in:30d,60d,90d,custom',
            'end_date' => 'required_if:horizon,custom|nullable|date|after:base_date',
            'scenario' => 'in:base,optimistic,pessimistic',
            'opening_balance' => 'nullable|numeric',
            'minimum_balance_threshold' => 'nullable|numeric|min:0',
            'assumptions' => 'nullable|array',
        ]);

        $forecast = $this->service->generate($validated, auth()->id());

        return response()->json([
            'forecast' => $forecast,
            'summary' => $this->service->buildSummary($forecast),
        ], 201);
    }

    public function show(CashFlowForecast $forecast): JsonResponse
    {
        return response()->json([
            'forecast' => $forecast->load(['items', 'createdBy']),
            'summary' => $this->service->buildSummary($forecast),
        ]);
    }

    public function destroy(CashFlowForecast $forecast): JsonResponse
    {
        $forecast->update(['status' => 'archived']);

        return response()->json(['message' => 'Forecast archived']);
    }

    // ─── Timeline (chart data) ───────────────────────────────────────────────

    public function timeline(CashFlowForecast $forecast): JsonResponse
    {
        return response()->json([
            'forecast_id' => $forecast->id,
            'period' => [
                'from' => $forecast->base_date->toDateString(),
                'to' => $forecast->end_date->toDateString(),
            ],
            'timeline' => $this->service->buildTimeline($forecast),
        ]);
    }

    // ─── Summary KPIs ────────────────────────────────────────────────────────

    public function summary(CashFlowForecast $forecast): JsonResponse
    {
        return response()->json($this->service->buildSummary($forecast));
    }

    // ─── Scenario Comparison ─────────────────────────────────────────────────

    public function compareScenarios(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_date' => 'required|date',
            'horizon' => 'required|in:30d,60d,90d',
            'opening_balance' => 'nullable|numeric',
            'minimum_balance_threshold' => 'nullable|numeric|min:0',
        ]);

        $scenarios = $this->service->compareScenarios($validated, auth()->id());

        return response()->json(['scenarios' => $scenarios]);
    }

    // ─── Manual items ────────────────────────────────────────────────────────

    public function addItem(Request $request, CashFlowForecast $forecast): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'category' => 'required|string|max:100',
            'type' => 'required|in:inflow,outflow',
            'source' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'probability' => 'nullable|numeric|between:0,100',
            'is_actual' => 'boolean',
        ]);

        $item = $this->service->addItem($forecast, $validated);

        return response()->json($item, 201);
    }

    public function removeItem(CashFlowForecast $forecast, int $itemId): JsonResponse
    {
        $item = $forecast->items()->findOrFail($itemId);
        $item->delete();

        $this->service->recalculate($forecast);

        return response()->json(['message' => 'Item removed']);
    }

    // ─── Treasury Alerts ─────────────────────────────────────────────────────

    public function alerts(): JsonResponse
    {
        return response()->json(TreasuryAlert::with('createdBy')->paginate(15));
    }

    public function storeAlert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:low_balance,high_balance,large_outflow,negative_forecast',
            'threshold_amount' => 'required|numeric',
            'days_lookahead' => 'required|integer|min:1|max:365',
            'severity' => 'required|in:warning,critical',
            'notification_channels' => 'nullable|array',
        ]);

        $alert = TreasuryAlert::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return response()->json($alert, 201);
    }

    public function updateAlert(Request $request, TreasuryAlert $alert): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'threshold_amount' => 'sometimes|numeric',
            'days_lookahead' => 'sometimes|integer|min:1|max:365',
            'severity' => 'sometimes|in:warning,critical',
            'is_active' => 'sometimes|boolean',
            'notification_channels' => 'nullable|array',
        ]);

        $alert->update($validated);

        return response()->json($alert);
    }

    public function destroyAlert(TreasuryAlert $alert): JsonResponse
    {
        $alert->delete();

        return response()->json(['message' => 'Alert deleted']);
    }

    public function evaluateAlerts(CashFlowForecast $forecast): JsonResponse
    {
        $triggered = $this->service->evaluateAlerts($forecast);

        return response()->json([
            'forecast_id' => $forecast->id,
            'triggered' => $triggered,
            'total' => count($triggered),
        ]);
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $horizon = $request->input('horizon', '90d');

        // Auto-generate a "live" base forecast if none is active
        $forecast = CashFlowForecast::where('status', 'active')
            ->where('horizon', $horizon)
            ->latest()
            ->first();

        if (! $forecast) {
            $forecast = $this->service->generate([
                'name' => "Auto-generated {$horizon} forecast",
                'base_date' => today()->toDateString(),
                'horizon' => $horizon,
                'status' => 'draft',
            ], auth()->id());

            $forecast->update(['status' => 'active']);
        }

        $summary = $this->service->buildSummary($forecast);
        $timeline = $this->service->buildTimeline($forecast);
        $triggered = $this->service->evaluateAlerts($forecast);

        return response()->json([
            'forecast' => $forecast,
            'summary' => $summary,
            'timeline' => $timeline,
            'alerts' => $triggered,
            'active_alerts' => TreasuryAlert::where('is_active', true)->count(),
        ]);
    }
}
