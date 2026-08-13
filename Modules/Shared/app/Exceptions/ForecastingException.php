<?php

declare(strict_types=1);

namespace Modules\Shared\Exceptions;

class ForecastingException extends BaseException
{
    protected string $errorCode = 'FORECASTING_ERROR';

    public static function insufficientHistoricalData(int $available, int $required, array $context = []): self
    {
        return new self(
            "Insufficient historical data. Available: {$available} days, Required: {$required} days",
            400,
            null,
            array_merge(['available' => $available, 'required' => $required], $context)
        );
    }

    public static function modelTrainingFailed(string $modelType, string $reason = '', array $context = []): self
    {
        return new self(
            "Model training failed for {$modelType}" . ($reason ? ": {$reason}" : ''),
            500,
            null,
            array_merge(['model_type' => $modelType, 'reason' => $reason], $context)
        );
    }

    public static function invalidModelType(string $modelType, array $validTypes = [], array $context = []): self
    {
        return new self(
            "Invalid model type: {$modelType}",
            400,
            null,
            array_merge(['model_type' => $modelType, 'valid_types' => $validTypes], $context)
        );
    }

    public static function accuracyThresholdNotMet(float $accuracy, float $threshold, array $context = []): self
    {
        return new self(
            "Forecast accuracy ({$accuracy}%) below threshold ({$threshold}%)",
            422,
            null,
            array_merge(['accuracy' => $accuracy, 'threshold' => $threshold], $context)
        );
    }
}
