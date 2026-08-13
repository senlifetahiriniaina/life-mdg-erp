<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\BiAnomaly;
use Modules\BI\Models\PredictiveModel;
use Modules\BI\Services\PredictiveAnalyticsService;

/**
 * @group BI - Predictive Analytics
 *
 * Time-series forecasting, anomaly detection, and trend analysis.
 */
class PredictiveAnalyticsController extends Controller
{
    public function __construct(
        private readonly PredictiveAnalyticsService $service,
    ) {}

    // ── Predictive Models ─────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $models = PredictiveModel::orderByDesc('updated_at')->get();

        return response()->json([
            'data' => $models,
            'total' => $models->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'entity_type' => 'required|string|max:100',
            'model_type' => 'sometimes|in:linear_regression,moving_average,exponential_smoothing,arima_simple',
            'forecast_horizon_days' => 'sometimes|integer|min:1|max:365',
        ]);

        $model = PredictiveModel::create($validated);

        return response()->json($model, 201);
    }

    public function forecasts(PredictiveModel $model): JsonResponse
    {
        $forecasts = $model->forecasts()->orderBy('forecast_date')->get();

        return response()->json([
            'data' => $forecasts,
            'total' => $forecasts->count(),
        ]);
    }

    public function train(Request $request, PredictiveModel $model): JsonResponse
    {
        $validated = $request->validate([
            'data_points' => 'required|array|min:2',
            'data_points.*.date' => 'required|string',
            'data_points.*.value' => 'required|numeric',
            'model_type' => 'sometimes|in:linear_regression,moving_average,exponential_smoothing,arima_simple',
            'periods' => 'sometimes|integer|min:1',
        ]);

        if (isset($validated['model_type'])) {
            $model->update(['model_type' => $validated['model_type']]);
        }

        $dataPoints = $validated['data_points'];
        $modelType = $model->fresh()->model_type;

        if ($modelType === 'moving_average') {
            $periods = $validated['periods'] ?? 7;
            $model = $this->service->trainMovingAverage($model, $dataPoints, $periods);
        } else {
            $model = $this->service->trainLinearRegression($model, $dataPoints);
        }

        return response()->json($model->fresh());
    }

    public function generate(Request $request, PredictiveModel $model): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $forecasts = $this->service->generateForecasts($model, $validated['days']);

        return response()->json([
            'data' => $forecasts,
            'total' => count($forecasts),
        ], 201);
    }

    // ── Anomalies ─────────────────────────────────────────────────────

    public function listAnomalies(Request $request): JsonResponse
    {
        $query = BiAnomaly::query()->orderByDesc('detected_at');

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $anomalies = $query->get();

        return response()->json([
            'data' => $anomalies,
            'total' => $anomalies->count(),
        ]);
    }

    public function detectAnomalies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|max:100',
            'data_points' => 'required|array|min:2',
            'data_points.*.date' => 'required|string',
            'data_points.*.value' => 'required|numeric',
            'z_threshold' => 'sometimes|numeric|min:1|max:5',
        ]);

        $anomalies = $this->service->detectAnomalies(
            $validated['entity_type'],
            $validated['data_points'],
            (float) ($validated['z_threshold'] ?? 2.0),
        );

        return response()->json([
            'data' => $anomalies,
            'total' => count($anomalies),
        ], 201);
    }

    public function acknowledge(BiAnomaly $anomaly): JsonResponse
    {
        $anomaly->acknowledge(auth()->id());

        return response()->json($anomaly->fresh());
    }

    // ── Analytics ─────────────────────────────────────────────────────

    public function revenueTrend(Request $request): JsonResponse
    {
        $months = max(1, min((int) ($request->months ?? 12), 36));
        $result = $this->service->revenueTrend($months);

        return response()->json($result);
    }

    public function growthRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|max:100',
            'periods' => 'sometimes|integer|min:2|max:36',
        ]);

        $periods = (int) ($validated['periods'] ?? 12);

        // Fetch monthly revenue trend and compute period-over-period growth rates
        $trend = $this->service->revenueTrend($periods);
        $months = $trend['months'];

        $growthRates = [];
        foreach ($months as $month) {
            $growthRates[] = [
                'period' => $month['month'],
                'entity_type' => $validated['entity_type'],
                'growth_rate' => $month['growth_rate'],
                'revenue' => $month['revenue'],
            ];
        }

        return response()->json([
            'data' => $growthRates,
            'total' => count($growthRates),
        ]);
    }
}
