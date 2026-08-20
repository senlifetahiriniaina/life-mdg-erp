<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityScore;
use Modules\CRM\Models\ScoringRule;
use Modules\CRM\Services\OpportunityScoringService;

/**
 * @group Controllers - Opportunity Scoring
 *
 * Manage Opportunity Scoring resources.
 */
class OpportunityScoringController extends Controller
{
    public function __construct(private readonly OpportunityScoringService $scoringService) {}

    public function scoreOpportunity(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);

        $score = $this->scoringService->score($opportunity);

        return response()->json([
            'opportunity_id' => $opportunity->id,
            'score' => [
                'total_score' => $score->total_score,
                'grade' => $score->grade,
                'engagement' => $score->engagement_score,
                'fit' => $score->fit_score,
                'velocity' => $score->velocity_score,
                'history' => $score->history_score,
            ],
            'action' => $score->getRecommendedAction(),
            'win_probability' => $score->win_probability,
        ]);
    }

    public function showScore(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);

        $score = $this->scoringService->getScore($opportunity);

        return response()->json([
            'opportunity_id' => $opportunity->id,
            'total_score' => $score->total_score,
            'grade' => $score->grade,
            'is_high_value' => $score->isHighValue(),
            'win_probability' => $score->win_probability,
            'engagement_score' => $score->engagement_score,
            'fit_score' => $score->fit_score,
            'velocity_score' => $score->velocity_score,
            'history_score' => $score->history_score,
        ]);
    }

    public function recordSignal(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);

        $validated = $request->validate([
            'signal_type' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $signal = $this->scoringService->recordSignal(
            $opportunity,
            $validated['signal_type'],
            description: $validated['description'] ?? null
        );

        return response()->json([
            'id' => $signal->id,
            'signal_type' => $signal->signal_type,
            'score_impact' => $signal->score_impact,
            'description' => $signal->description,
            'occurred_at' => $signal->occurred_at,
        ], 201);
    }

    public function signals(Request $request, Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);

        $signals = $opportunity->engagementSignals()
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn ($signal) => [
                'id' => $signal->id,
                'signal_type' => $signal->signal_type,
                'score_impact' => $signal->score_impact,
                'description' => $signal->description,
                'occurred_at' => $signal->occurred_at,
            ]);

        return response()->json(['data' => $signals]);
    }

    public function leaderboard(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $limit = (int) $request->get('limit', 20);
        $leaderboard = $this->scoringService->leaderboard($limit, $request->user()->company_id);

        return response()->json(['data' => $leaderboard->values()]);
    }

    public function forecast(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $forecast = $this->scoringService->pipelineForecast($request->user()->company_id);

        return response()->json($forecast);
    }

    public function indexRules(Request $request)
    {
        $this->authorize('viewAny', ScoringRule::class);

        $rules = ScoringRule::all()
            ->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'category' => $rule->category,
                'condition_field' => $rule->condition_field,
                'condition_operator' => $rule->condition_operator,
                'condition_value' => $rule->condition_value,
                'points' => $rule->points,
                'weight' => $rule->weight,
                'is_active' => $rule->is_active,
            ]);

        return response()->json($rules->values());
    }

    public function storeRule(Request $request)
    {
        $this->authorize('create', ScoringRule::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:engagement,fit,velocity,history',
            'condition_field' => 'required|string',
            'condition_operator' => 'required|string|in:eq,gt,gte,lt,lte,contains',
            'condition_value' => 'required|string',
            'points' => 'required|integer|min:1|max:100',
            'weight' => 'nullable|numeric|min:0.1|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        $rule = ScoringRule::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'condition_field' => $validated['condition_field'],
            'condition_operator' => $validated['condition_operator'],
            'condition_value' => $validated['condition_value'],
            'points' => $validated['points'],
            'weight' => $validated['weight'] ?? 1.0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($rule, 201);
    }

    public function updateRule(Request $request, ScoringRule $rule)
    {
        $this->authorize('update', $rule);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'category' => 'nullable|string|in:engagement,fit,velocity,history',
            'condition_field' => 'nullable|string',
            'condition_operator' => 'nullable|string|in:eq,gt,gte,lt,lte,contains',
            'condition_value' => 'nullable|string',
            'points' => 'nullable|integer|min:1|max:100',
            'weight' => 'nullable|numeric|min:0.1|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        $rule->update($validated);

        return response()->json($rule);
    }

    public function destroyRule(ScoringRule $rule)
    {
        $this->authorize('delete', $rule);

        $rule->delete();

        return response()->json(null, 204);
    }

    public function updateScoringRule(Request $request, ScoringRule $scoringRule)
    {
        return $this->updateRule($request, $scoringRule);
    }

    public function destroyScoringRule(ScoringRule $scoringRule)
    {
        return $this->destroyRule($scoringRule);
    }

    public function scoreAll(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $results = $this->scoringService->scoreAll($request->user()->company_id);

        return response()->json($results);
    }

    public function getScores(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        // Chantier "CRM tenant-isolation follow-up": previously listed every company's scores
        // (only a class-level `viewAny` check, no per-record/tenant scoping at all).
        $scores = OpportunityScore::query()
            ->whereHas('opportunity', fn ($q) => $q->where('tenant_id', $request->user()->company_id));

        if ($request->has('min_score')) {
            $scores->where('total_score', '>=', (int) $request->min_score);
        }

        if ($request->has('max_score')) {
            $scores->where('total_score', '<=', (int) $request->max_score);
        }

        return response()->json(
            $scores->with('opportunity')
                ->orderBy('total_score', 'desc')
                ->paginate(15)
        );
    }

    public function indexScores(Request $request)
    {
        return $this->getScores($request);
    }
}
