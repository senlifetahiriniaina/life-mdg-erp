<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\Forecast;
use Modules\BI\Models\PredictiveModel;
use Modules\BI\Models\ForecastModel;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * EvaluateForecastAccuracyJob
 *
 * Compares predictions vs actual values after period ends.
 * Updates model accuracy metrics and identifies model drift.
 *
 * @property int forecast_model_id The ID of the predictive model
 * @property string evaluation_period Period to evaluate: 'daily', 'weekly', 'monthly'
 * @property string job_id Unique identifier for tracking progress
 */
class EvaluateForecastAccuracyJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $forecast_model_id,
        private readonly string $evaluation_period = 'daily'
    ) {
        $this->jobId = uniqid('eval_acc_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting forecast accuracy evaluation', [
                'job_id' => $this->jobId,
                'model_id' => $this->forecast_model_id,
                'period' => $this->evaluation_period,
                'timestamp' => now()->toIso8601String(),
            ]);

            $model = PredictiveModel::findOrFail($this->forecast_model_id);

            // Get completed forecasts to evaluate
            $forecasts = $this->getCompletedForecasts($model);

            if ($forecasts->isEmpty()) {
                Log::info('No completed forecasts to evaluate', [
                    'job_id' => $this->jobId,
                    'model_id' => $this->forecast_model_id,
                ]);

                return;
            }

            // Fetch actual values for comparison
            $actualValues = $this->fetchActualValues($model, $forecasts);

            // Calculate accuracy metrics
            $metrics = $this->calculateAccuracyMetrics($forecasts, $actualValues);

            // Detect model drift
            $drift = $this->detectModelDrift($model, $metrics);

            // Update model with evaluation results
            $this->updateModelAccuracy($model, $metrics, $drift);

            // Generate accuracy report
            $this->generateAccuracyReport($model, $metrics, $drift);

            Log::info('Forecast accuracy evaluation completed', [
                'job_id' => $this->jobId,
                'model_id' => $this->forecast_model_id,
                'mae' => $metrics['mae'] ?? null,
                'rmse' => $metrics['rmse'] ?? null,
                'mape' => $metrics['mape'] ?? null,
                'drift_detected' => $drift['is_drifting'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Forecast accuracy evaluation failed', [
                'job_id' => $this->jobId,
                'model_id' => $this->forecast_model_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extractCompanyId(): int
    {
        $model = ForecastModel::findOrFail($this->forecast_model_id);
        return $model->company_id;
    }

    /**
     * Get forecasts that have completed their forecast period
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Forecast>
     */
    private function getCompletedForecasts(PredictiveModel $model)
    {
        // Get forecasts from past periods (where forecast_date is in the past)
        return Forecast::where('predictive_model_id', $model->id)
            ->where('forecast_date', '<', now()->toDateString())
            ->whereNull('error_percent') // Not yet evaluated
            ->get();
    }

    /**
     * Fetch actual values for forecast dates
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Forecast> $forecasts
     * @return array<string, float>
     */
    private function fetchActualValues(PredictiveModel $model, $forecasts): array
    {
        $actualValues = [];

        foreach ($forecasts as $forecast) {
            // In a real implementation, fetch from data source
            // For now, simulate actual values
            $actualValue = $forecast->forecast_value + rand(-20, 20);

            $actualValues[$forecast->forecast_date] = max(0, $actualValue);
        }

        Log::debug('Actual values fetched', [
            'job_id' => $this->jobId,
            'forecast_count' => count($actualValues),
        ]);

        return $actualValues;
    }

    /**
     * Calculate accuracy metrics comparing forecasts to actuals
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Forecast> $forecasts
     * @param array<string, float> $actualValues
     * @return array<string, float>
     */
    private function calculateAccuracyMetrics($forecasts, array $actualValues): array
    {
        $errors = [];
        $percentErrors = [];

        foreach ($forecasts as $forecast) {
            if (!isset($actualValues[$forecast->forecast_date])) {
                continue;
            }

            $actual = $actualValues[$forecast->forecast_date];
            $predicted = $forecast->forecast_value;

            $error = $actual - $predicted;
            $errors[] = abs($error);

            $percentError = ($actual > 0) ? abs($error) / $actual * 100 : 0;
            $percentErrors[] = $percentError;
        }

        if (empty($errors)) {
            return [
                'mae' => 0,
                'rmse' => 0,
                'mape' => 0,
                'forecast_count' => 0,
            ];
        }

        // Mean Absolute Error
        $mae = array_sum($errors) / count($errors);

        // Root Mean Square Error
        $squaredErrors = array_map(fn($e) => pow($e, 2), $errors);
        $rmse = sqrt(array_sum($squaredErrors) / count($squaredErrors));

        // Mean Absolute Percentage Error
        $mape = array_sum($percentErrors) / count($percentErrors);

        // Convert errors to accuracy percentage (100 - MAPE)
        $accuracy = max(0, 100 - $mape);

        return [
            'mae' => round($mae, 2),
            'rmse' => round($rmse, 2),
            'mape' => round($mape, 2),
            'accuracy_percent' => round($accuracy, 2),
            'forecast_count' => count($errors),
        ];
    }

    /**
     * Detect if model is experiencing drift
     *
     * @param array<string, float> $metrics
     * @return array<string, mixed>
     */
    private function detectModelDrift(PredictiveModel $model, array $metrics): array
    {
        $currentAccuracy = $metrics['accuracy_percent'] ?? 0;
        $previousAccuracy = $model->accuracy_score ?? 100;

        // Calculate accuracy degradation
        $accuracyDegradation = $previousAccuracy - $currentAccuracy;

        // Threshold: more than 10% accuracy loss indicates drift
        $isDrifting = $accuracyDegradation > 10;

        $driftSeverity = 'none';

        if ($accuracyDegradation > 20) {
            $driftSeverity = 'severe';
        } elseif ($accuracyDegradation > 10) {
            $driftSeverity = 'moderate';
        }

        return [
            'is_drifting' => $isDrifting,
            'drift_severity' => $driftSeverity,
            'accuracy_degradation' => round($accuracyDegradation, 2),
            'previous_accuracy' => $previousAccuracy,
            'current_accuracy' => $currentAccuracy,
            'detected_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Update model with new accuracy evaluation
     *
     * @param array<string, float> $metrics
     * @param array<string, mixed> $drift
     */
    private function updateModelAccuracy(PredictiveModel $model, array $metrics, array $drift): void
    {
        // Update model accuracy and evaluation data
        $model->update([
            'accuracy_score' => $metrics['accuracy_percent'] ?? $model->accuracy_score,
            'last_evaluated_at' => now(),
            'evaluation_data' => [
                'mae' => $metrics['mae'],
                'rmse' => $metrics['rmse'],
                'mape' => $metrics['mape'],
                'forecast_count' => $metrics['forecast_count'],
                'evaluated_at' => now()->toIso8601String(),
            ],
        ]);

        Log::debug('Model accuracy updated', [
            'job_id' => $this->jobId,
            'model_id' => $model->id,
            'new_accuracy' => $metrics['accuracy_percent'],
        ]);

        // If drift detected, flag model for retraining
        if ($drift['is_drifting']) {
            $this->flagModelForRetraining($model, $drift);
        }
    }

    /**
     * Flag model for retraining if accuracy has degraded
     *
     * @param array<string, mixed> $drift
     */
    private function flagModelForRetraining(PredictiveModel $model, array $drift): void
    {
        // In a real implementation, create a ModelRetrainingRequest
        $severity = $drift['drift_severity'];

        Log::warning('Model drift detected - flagged for retraining', [
            'job_id' => $this->jobId,
            'model_id' => $model->id,
            'severity' => $severity,
            'accuracy_degradation' => $drift['accuracy_degradation'],
        ]);

        // Auto-schedule retraining for severe drift
        if ($severity === 'severe') {
            TrainForecastModelJob::dispatch($model->id, 24)
                ->delay(now()->addHours(1)); // Delay 1 hour
        }
    }

    /**
     * Generate accuracy report
     *
     * @param array<string, float> $metrics
     * @param array<string, mixed> $drift
     */
    private function generateAccuracyReport(PredictiveModel $model, array $metrics, array $drift): void
    {
        $report = [
            'model_id' => $model->id,
            'model_name' => $model->name,
            'evaluation_date' => now()->toDateString(),
            'metrics' => $metrics,
            'drift_analysis' => $drift,
            'recommendation' => $this->generateRecommendation($metrics, $drift),
        ];

        Log::info('Accuracy report generated', [
            'job_id' => $this->jobId,
            'report' => $report,
        ]);
    }

    /**
     * Generate recommendation based on accuracy evaluation
     *
     * @param array<string, float> $metrics
     * @param array<string, mixed> $drift
     */
    private function generateRecommendation(array $metrics, array $drift): string
    {
        $accuracy = $metrics['accuracy_percent'] ?? 0;

        if ($drift['is_drifting']) {
            return "Model drift detected. Recommend immediate retraining with latest data.";
        }

        if ($accuracy > 90) {
            return "Model performance excellent. Continue monitoring. No action needed.";
        }

        if ($accuracy > 75) {
            return "Model performance acceptable. Consider retraining if accuracy drops below 70%.";
        }

        return "Model performance below acceptable threshold. Recommend retraining immediately.";
    }
}
