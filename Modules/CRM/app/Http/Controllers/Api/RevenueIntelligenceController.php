<?php

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\RevenueInsight;
use Modules\CRM\Models\RevenueTrend;
use Modules\CRM\Models\RevenueAnomaly;

class RevenueIntelligenceController extends Controller
{
    public function getInsights(Request $request): JsonResponse
    {
        $insights = RevenueInsight::query()
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->insight_type, fn ($q) => $q->where('insight_type', $request->insight_type))
            ->where('status', 'active')
            ->orderBy('insight_generated_at', 'desc')
            ->paginate(15);

        return response()->json($insights);
    }

    public function generateInsight(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'insight_type'  => 'required|in:trend,anomaly,opportunity,risk,recommendation',
            'category'      => 'required|in:sales_performance,pipeline_health,forecast_accuracy,team_efficiency',
            'title'         => 'required|string',
            'description'   => 'required|string',
            'data'          => 'nullable|json',
            'impact_score'  => 'nullable|integer|min:1|max:10',
        ]);

        $insight = RevenueInsight::create(array_merge($validated, [
            'insight_generated_at' => now(),
        ]));

        return response()->json($insight, 201);
    }

    public function getTrends(Request $request): JsonResponse
    {
        $trends = RevenueTrend::query()
            ->when($request->metric_name, fn ($q) => $q->where('metric_name', $request->metric_name))
            ->when($request->dimension, fn ($q) => $q->where('dimension', $request->dimension))
            ->orderBy('period_start', 'desc')
            ->paginate(20);

        return response()->json($trends);
    }

    public function recordTrend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric_name'       => 'required|string',
            'dimension'         => 'nullable|string',
            'dimension_value'   => 'nullable|string',
            'period_start'      => 'required|date',
            'period_end'        => 'required|date',
            'current_value'     => 'required|numeric',
            'previous_value'    => 'nullable|numeric',
            'trend_direction'   => 'required|in:up,down,flat',
        ]);

        $trend = RevenueTrend::create($validated);

        return response()->json($trend, 201);
    }

    public function getAnomalies(Request $request): JsonResponse
    {
        $anomalies = RevenueAnomaly::query()
            ->when($request->severity, fn ($q) => $q->where('severity', $request->severity))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('detected_at', 'desc')
            ->paginate(15);

        return response()->json($anomalies);
    }

    public function detectAnomaly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anomaly_type'    => 'required|in:unusual_spike,unexpected_drop,outlier_value,forecast_deviation',
            'metric_name'     => 'required|string',
            'dimension'       => 'nullable|string',
            'dimension_value' => 'nullable|string',
            'detected_value'  => 'required|numeric',
            'expected_value'  => 'required|numeric',
            'severity'        => 'required|in:low,medium,high',
        ]);

        $deviationPct = (($validated['detected_value'] - $validated['expected_value']) /
                        $validated['expected_value']) * 100;

        $anomaly = RevenueAnomaly::create(array_merge($validated, [
            'deviation_pct' => $deviationPct,
            'detected_at'   => now(),
            'status'        => 'detected',
        ]));

        return response()->json($anomaly, 201);
    }

    public function resolveAnomaly(RevenueAnomaly $anomaly): JsonResponse
    {
        $anomaly->update(['status' => 'resolved']);

        return response()->json(['message' => 'Anomaly resolved']);
    }

    public function getPerformanceSummary(): JsonResponse
    {
        $activeInsights = RevenueInsight::where('status', 'active')->count();
        $criticalAnomalies = RevenueAnomaly::where('severity', 'high')
            ->where('status', 'detected')
            ->count();
        $recentTrends = RevenueTrend::orderBy('period_start', 'desc')->limit(5)->get();

        return response()->json([
            'active_insights'      => $activeInsights,
            'critical_anomalies'   => $criticalAnomalies,
            'recent_trends'        => $recentTrends,
        ]);
    }
}
