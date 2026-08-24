<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyRitualSession;
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
        // Chantier 32.27: `exists:strategy_plans,id` alone doesn't check
        // ownership, and AiStrategyAdvisorService::getInsights() itself does
        // a bare find($planId) with no tenant filter either — confirmed
        // empirically that any user could get real AI insights (plan name/
        // vision/mission/objective progress) built from another company's
        // real strategic plan just by passing its id.
        $this->planInTenant($request->integer('plan_id'), $tenantId);

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

        // Chantier 32.27: this endpoint generates a full board-level
        // narrative (real plan name/vision/mission/objective progress) from
        // ANY plan_id, with zero ownership check — the most severe of this
        // controller's cross-tenant leaks, confirmed empirically.
        $this->planInTenant($request->integer('plan_id'), $this->tenantId($request));

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

        // Chantier 32.27: `exists:strategy_ritual_sessions,id` alone doesn't
        // check that the session's ritual belongs to the caller's own
        // company — confirmed empirically that any user could read another
        // company's real ritual session decisions/action items via the
        // generated summary.
        $sessionId = $request->integer('session_id');
        $tenantId  = $this->tenantId($request);
        $session   = StrategyRitualSession::with('ritual')->findOrFail($sessionId);
        abort_if((string) ($session->ritual?->tenant_id ?? '') !== $tenantId, 404);

        $summary = $this->advisor->generateRitualSummary($sessionId);

        return response()->json(['summary' => $summary]);
    }

    /**
     * 404 unless the plan belongs to the caller's own tenant.
     */
    private function planInTenant(int $planId, string $tenantId): StrategyPlan
    {
        $plan = StrategyPlan::findOrFail($planId);

        abort_if((string) ($plan->tenant_id ?? '') !== $tenantId, 404);

        return $plan;
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
