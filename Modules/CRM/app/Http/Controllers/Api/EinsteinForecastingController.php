<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Services\EinsteinForecastingService;

/**
 * @group Controllers - Einstein Forecasting
 *
 * Manage Einstein Forecasting resources.
 */
class EinsteinForecastingController extends Controller
{
    public function __construct(private readonly EinsteinForecastingService $forecastingService) {}

    public function generateForecast(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $request->validate([
            'timeframe' => 'required|in:monthly,quarterly,annual',
            'include_probability' => 'boolean',
        ]);

        $forecast = $this->forecastingService->generateWeightedForecast(
            $request->input('timeframe'),
            $request->boolean('include_probability', true),
            $request->user()->company_id
        );

        return response()->json($forecast);
    }

    public function byRepresentative(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $repId = $request->filled('rep_id') ? (int) $request->input('rep_id') : null;
        $forecast = $this->forecastingService->forecastByRepresentative($repId, $request->user()->company_id);

        return response()->json($forecast);
    }

    public function byProduct(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $productId = $request->input('product_id');
        $forecast = $this->forecastingService->forecastByProduct($productId);

        return response()->json($forecast);
    }

    public function confidence(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $confidenceAnalysis = $this->forecastingService->analyzeConfidence($request->user()->company_id);

        return response()->json([
            'high_confidence_deals' => $confidenceAnalysis['high'],
            'medium_confidence_deals' => $confidenceAnalysis['medium'],
            'low_confidence_deals' => $confidenceAnalysis['low'],
            'confidence_index' => $confidenceAnalysis['index'],
            'recommendations' => $confidenceAnalysis['recommendations'],
        ]);
    }

    public function metrics(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $metrics = $this->forecastingService->calculateForecastMetrics($request->user()->company_id);

        return response()->json([
            'accuracy_rate' => $metrics['accuracy'],
            'win_probability' => $metrics['win_rate'],
            'average_deal_size' => $metrics['avg_size'],
            'sales_cycle_length' => $metrics['cycle_length'],
            'pipeline_health_score' => $metrics['health_score'],
        ]);
    }

    public function adjust(Request $request)
    {
        $this->authorize('create', Opportunity::class);

        $request->validate([
            'adjustment_type' => 'required|in:quota,forecast,scenario',
            'adjustment_factor' => 'required|numeric|between:0.5,2.0',
            'reason' => 'nullable|string',
        ]);

        $adjusted = $this->forecastingService->adjustForecast(
            $request->input('adjustment_type'),
            (float) $request->input('adjustment_factor'),
            $request->input('reason'),
            $request->user()->company_id
        );

        return response()->json([
            'original_forecast' => $adjusted['original'],
            'adjusted_forecast' => $adjusted['adjusted'],
            'adjustment_factor' => $adjusted['factor'],
            'confidence_impact' => $adjusted['confidence_impact'],
        ]);
    }
}
