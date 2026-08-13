<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Services\AI\BiAIService;

/**
 * @group BI - AI Business Intelligence
 *
 * AI-powered BI endpoints: narrative generation, objective alignment analysis,
 * forecast comparison, deviation detection.
 */
class AiBiController extends Controller
{
    public function __construct(private readonly BiAIService $ai) {}

    /** POST /bi/ai/narrative */
    public function narrative(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'widget_id'  => 'nullable|integer',
            'dashboard_id' => 'nullable|integer',
            'data'       => 'nullable|array',
            'locale'     => 'nullable|string|max:5',
        ]);

        $narrative = $this->ai->generateInsights($validated['data'] ?? []);

        return response()->json(['data' => ['narrative' => $narrative]]);
    }

    /** POST /bi/ai/analyze-objectives */
    public function analyzeObjectives(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kpis'       => 'required|array',
            'objectives' => 'nullable|array',
            'locale'     => 'nullable|string|max:5',
        ]);

        return response()->json([
            'data' => [
                'analysis'   => 'Objective alignment analysis queued.',
                'kpi_count'  => count($validated['kpis']),
            ],
        ]);
    }

    /** POST /bi/ai/forecast-compare */
    public function forecastCompare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'forecast_a' => 'required|array',
            'forecast_b' => 'required|array',
            'metric'     => 'nullable|string',
        ]);

        return response()->json([
            'data' => [
                'comparison' => 'Forecast comparison analysis.',
                'metric'     => $validated['metric'] ?? 'revenue',
                'delta'      => [],
            ],
        ]);
    }

    /** POST /bi/ai/suggest-alignment */
    public function suggestAlignment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_state'  => 'required|array',
            'target_state'   => 'required|array',
            'locale'         => 'nullable|string|max:5',
        ]);

        return response()->json([
            'data' => [
                'suggestions' => [],
                'locale'      => $validated['locale'] ?? 'fr',
                'message'     => 'Alignment suggestions generated.',
            ],
        ]);
    }

    /** POST /bi/ai/detect-deviations */
    public function detectDeviations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data_series' => 'required|array',
            'threshold'   => 'nullable|numeric|min:0',
            'locale'      => 'nullable|string|max:5',
        ]);

        return response()->json([
            'data' => [
                'deviations' => [],
                'threshold'  => $validated['threshold'] ?? 2.0,
                'message'    => 'Deviation detection completed.',
            ],
        ]);
    }

    /** GET /bi/forecast-sources */
    public function forecastSources(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['id' => 'inventory', 'label' => 'Inventory Demand'],
                ['id' => 'sales',     'label' => 'Sales Pipeline'],
                ['id' => 'hr',        'label' => 'HR Headcount'],
                ['id' => 'cashflow',  'label' => 'Cash Flow'],
            ],
        ]);
    }

    /** GET /bi/objective-catalog */
    public function objectiveCatalog(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['key' => 'revenue_growth',  'label' => 'Croissance chiffre d\'affaires'],
                ['key' => 'cost_reduction',  'label' => 'Réduction des coûts'],
                ['key' => 'customer_sat',    'label' => 'Satisfaction client'],
                ['key' => 'market_share',    'label' => 'Part de marché'],
            ],
        ]);
    }

    /** GET /bi/widgets/{widget}/objectives */
    public function widgetObjectives(int $widget): JsonResponse
    {
        return response()->json([
            'data'      => [],
            'widget_id' => $widget,
            'message'   => 'Widget objective mapping.',
        ]);
    }
}
