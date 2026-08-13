<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\ForecastModel;
use Modules\BI\Models\ForecastPrediction;
use Modules\BI\Models\ForecastScenario;
use Modules\BI\Models\ModelRetrainingLog;

/**
 * @group BI - Forecasting
 *
 * Manage predictive analytics and forecasting models
 */
class ForecastingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ForecastModel::class);

        $models = ForecastModel::with(['creator'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($models);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ForecastModel::class);

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string',
            'model_type'         => 'required|string|in:linear_regression,arima,exponential_smoothing,prophet,lstm',
            'metric_name'        => 'required|string',
            'metric_source_id'   => 'required|integer',
            'data_frequency'     => 'string|in:hourly,daily,weekly,monthly',
            'lookback_days'      => 'integer|min:7',
            'forecast_horizon'   => 'integer|min:1',
            'model_parameters'   => 'nullable|array',
        ]);

        $model = ForecastModel::create([
            ...$validated,
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'status'     => 'draft',
        ]);

        return response()->json($model, 201);
    }

    public function show(ForecastModel $model): JsonResponse
    {
        $this->authorize('view', $model);
        $model->load(['predictions', 'scenarios', 'seasonalityPatterns', 'trendAnalysis']);

        return response()->json($model);
    }

    public function update(Request $request, ForecastModel $model): JsonResponse
    {
        $this->authorize('view', $model);

        $validated = $request->validate([
            'name'             => 'string|max:255',
            'description'      => 'nullable|string',
            'model_parameters' => 'nullable|array',
        ]);

        $model->update($validated);

        return response()->json($model);
    }

    public function train(Request $request, ForecastModel $model): JsonResponse
    {
        $this->authorize('train', $model);

        $model->train();

        // In a real implementation, this would queue a training job
        ModelRetrainingLog::create([
            'model_id'   => $model->id,
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json(['status' => 'training_started']);
    }

    public function deploy(ForecastModel $model): JsonResponse
    {
        $this->authorize('deploy', $model);

        if (! $model->isTrained()) {
            return response()->json(['error' => 'Model must be trained before deployment'], 422);
        }

        $model->deploy();

        return response()->json(['status' => $model->status]);
    }

    public function archive(ForecastModel $model): JsonResponse
    {
        $this->authorize('archive', $model);

        $model->archive();

        return response()->json(['status' => $model->status]);
    }

    public function delete(ForecastModel $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();

        return response()->json(null, 204);
    }

    public function predictions(Request $request, ForecastModel $model): JsonResponse
    {
        $this->authorize('viewPredictions', $model);

        $predictions = ForecastPrediction::where('model_id', $model->id)
            ->orderBy('prediction_date')
            ->paginate(20);

        return response()->json($predictions);
    }

    public function scenarios(ForecastModel $model): JsonResponse
    {
        $this->authorize('manageScenarios', $model);

        $scenarios = ForecastScenario::where('model_id', $model->id)
            ->latest()
            ->get();

        return response()->json($scenarios);
    }

    public function createScenario(Request $request, ForecastModel $model): JsonResponse
    {
        $this->authorize('manageScenarios', $model);

        $validated = $request->validate([
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string',
            'scenario_type'           => 'required|string|in:best_case,worst_case,realistic,custom',
            'parameters'              => 'array',
            'growth_rate_adjustment'  => 'nullable|numeric',
            'volatility_adjustment'   => 'nullable|numeric',
        ]);

        $scenario = ForecastScenario::create([
            'model_id'   => $model->id,
            'created_by' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json($scenario, 201);
    }

    public function scenarioPredictions(ForecastScenario $scenario): JsonResponse
    {
        $this->authorize('manageScenarios', $scenario->model);

        $predictions = $scenario->predictions()
            ->orderBy('prediction_date')
            ->paginate(20);

        return response()->json($predictions);
    }

    public function accuracy(ForecastModel $model): JsonResponse
    {
        $this->authorize('viewAccuracy', $model);

        return response()->json([
            'model_id'      => $model->id,
            'rmse'          => $model->rmse,
            'mae'           => $model->mae,
            'mape'          => $model->mape,
            'r_squared'     => $model->r_squared,
            'accuracy'      => $model->getAccuracyPercentage(),
            'trained_at'    => $model->trained_at,
            'last_retrained' => $model->last_retrained_at,
        ]);
    }

    public function retrainingLogs(Request $request, ForecastModel $model): JsonResponse
    {
        $this->authorize('view', $model);

        $logs = ModelRetrainingLog::where('model_id', $model->id)
            ->latest()
            ->paginate(10);

        return response()->json($logs);
    }

    public function markForRetraining(ForecastModel $model): JsonResponse
    {
        $this->authorize('train', $model);

        $model->markForRetraining();

        return response()->json(['next_retraining_at' => $model->next_retraining_at]);
    }
}
