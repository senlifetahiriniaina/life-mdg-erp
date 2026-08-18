<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Strategy\Services\AiStrategyAdvisorService;

class StrategyAdvisorController extends Controller
{
    public function __construct(private AiStrategyAdvisorService $advisor) {}

    public function insights(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:strategy_plans,id',
        ]);

        $tenantId = $this->tenantId($request);
        $insights = $this->advisor->getInsights($tenantId, $request->integer('plan_id'));

        return response()->json($insights);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $request->validate([
            'context' => 'required|string|max:1000',
        ]);

        $tenantId       = $this->tenantId($request);
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

    /**
     * Chantier 10 (Strategy): was $request->user()?->tenant_id ?? 'default' —
     * the phantom users.tenant_id column, never populated for real users, so
     * every tenant silently collapsed into one shared 'default' bucket (a
     * live cross-tenant leak). See StrategyPlanController::tenantId() for the
     * full rationale; fixed to the real company_id boundary column.
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }
}
