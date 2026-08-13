<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Services\AI\BiAIService;

/**
 * @group BI - BiAI
 *
 * AI-powered business intelligence insights.
 */
class BiAIController extends Controller
{
    public function __construct(private readonly BiAIService $ai) {}

    public function generateInsights(Request $request): JsonResponse
    {
        $data = $request->validate(['kpis' => 'required|array', 'period' => 'nullable|string']);

        return response()->json(['insights' => $this->ai->generateInsights($data['kpis'], $data['period'] ?? 'last 30 days')]);
    }

    public function detectTrends(Request $request): JsonResponse
    {
        $data = $request->validate(['history' => 'required|array']);

        return response()->json($this->ai->detectTrends($data['history']));
    }

    public function suggestKpis(Request $request): JsonResponse
    {
        $data = $request->validate(['industry' => 'required|string', 'existing_kpis' => 'nullable|array']);

        return response()->json($this->ai->suggestKpis($data['industry'], $data['existing_kpis'] ?? []));
    }

    public function recommendDashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
            'usage_history' => 'required|array',
            'available_kpis' => 'required|array',
        ]);

        return response()->json($this->ai->recommendDashboard($data['user_id'], $data['usage_history'], $data['available_kpis']));
    }
}
