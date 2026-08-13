<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Strategy\Services\AiStrategyAdvisorService;

class StrategyAdvisorController extends Controller
{
    public function __construct(private AiStrategyAdvisorService $advisor) {}

    public function insights(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:strategy_plans,id',
        ]);

        $tenantId = $request->header('X-Tenant-Id', $request->query('tenant_id', 'default'));
        $insights = $this->advisor->getInsights($tenantId, $request->integer('plan_id'));

        return response()->json($insights);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $request->validate([
            'context' => 'required|string|max:1000',
        ]);

        $tenantId       = $request->header('X-Tenant-Id', 'default');
        $recommendations = $this->advisor->getRecommendations($tenantId, $request->input('context'));

        return response()->json($recommendations);
    }

    public function boardReport(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:strategy_plans,id',
            'locale'  => 'nullable|string|in:fr,en,es,pt,ar,sw,mg,ha,zh,hi',
            'format'  => 'nullable|string|in:narrative,bullets,executive',
        ]);

        $report = $this->advisor->generateBoardReport(
            $request->integer('plan_id'),
            $request->input('locale', 'fr'),
            $request->input('format', 'narrative')
        );

        return response()->json(['report' => $report]);
    }

    public function ritualSummary(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|integer|exists:strategy_ritual_sessions,id',
        ]);

        $summary = $this->advisor->generateRitualSummary($request->integer('session_id'));

        return response()->json(['summary' => $summary]);
    }

    public function formulateOkr(Request $request): JsonResponse
    {
        $request->validate([
            'pillar_context'   => 'required|string|max:500',
            'objective_draft'  => 'required|string|max:500',
            'locale'           => 'nullable|string|in:fr,en',
        ]);

        $result = $this->advisor->helpFormulateOkr(
            $request->input('pillar_context'),
            $request->input('objective_draft'),
            $request->input('locale', 'fr')
        );

        return response()->json($result);
    }
}
