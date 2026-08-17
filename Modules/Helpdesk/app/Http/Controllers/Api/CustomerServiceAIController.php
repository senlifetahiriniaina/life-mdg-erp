<?php

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Helpdesk\Models\{
    SentimentScore,
    EmotionAnalysis,
    LanguageDetection,
    RoutingRule,
    EscalationPrediction,
    EscalationModel,
    UrgencyFactor,
    ResponseTemplate,
    ResponseSuggestion,
    SatisfactionPrediction,
    NPSPredictor,
    AgentMetric,
    AgentPerformanceTrend,
    AgentSkillAnalysis,
    AgentCoachingRecommendation,
    TeamBenchmarking,
    PerformanceGoal,
};

/**
 * @group Controllers - Customer Service AI
 *
 * Advanced AI-powered customer service features including sentiment analysis,
 * escalation prediction, AI-powered response suggestions, satisfaction prediction,
 * and comprehensive agent performance analytics.
 */
class CustomerServiceAIController extends Controller
{
    /**
     * Get sentiment analysis for a ticket
     *
     * @queryParam ticket_id integer Required. Ticket ID
     * @queryParam include_history boolean Include sentiment history trend
     */
    public function getSentimentAnalysis(Request $request): JsonResponse
    {
        $this->authorize('viewSentimentAnalysis');

        $ticketId = $request->query('ticket_id');
        $includeHistory = $request->boolean('include_history', false);

        $sentiment = SentimentScore::where('ticket_id', $ticketId)
            ->with('sentimentModel')
            ->when($includeHistory, fn($q) => $q->with('history'))
            ->latest('analyzed_at')
            ->first();

        if (!$sentiment) {
            return response()->json(['error' => 'No sentiment analysis found'], 404);
        }

        return response()->json([
            'sentiment' => $sentiment->sentiment,
            'confidence' => $sentiment->confidence,
            'scores' => [
                'positive' => $sentiment->positive_score,
                'negative' => $sentiment->negative_score,
                'neutral' => $sentiment->neutral_score,
            ],
            'language' => $sentiment->language_detected,
            'history' => $includeHistory ? $sentiment->history()->latest()->take(5)->get() : null,
        ]);
    }

    /**
     * Get emotion analysis for a ticket
     *
     * @queryParam ticket_id integer Required. Ticket ID
     */
    public function getEmotionAnalysis(Request $request): JsonResponse
    {
        $this->authorize('viewEmotionAnalysis');

        $ticketId = $request->query('ticket_id');

        $emotion = EmotionAnalysis::where('ticket_id', $ticketId)
            ->latest('analyzed_at')
            ->first();

        if (!$emotion) {
            return response()->json(['error' => 'No emotion analysis found'], 404);
        }

        return response()->json([
            'dominant_emotion' => $emotion->dominant_emotion,
            'emotional_state' => $emotion->emotional_state,
            'intensity' => $emotion->emotional_intensity,
            'emotions' => [
                'anger' => $emotion->anger_score,
                'frustration' => $emotion->frustration_score,
                'satisfaction' => $emotion->satisfaction_score,
                'confusion' => $emotion->confusion_score,
                'urgency' => $emotion->urgency_score,
                'disappointment' => $emotion->disappointment_score,
            ],
            'shift_detected' => $emotion->sentiment_shift_detected,
        ]);
    }

    /**
     * Get language detection for a ticket
     *
     * @queryParam ticket_id integer Required. Ticket ID
     */
    public function getLanguageDetection(Request $request): JsonResponse
    {
        $this->authorize('viewLanguageDetection');

        $ticketId = $request->query('ticket_id');

        $language = LanguageDetection::where('ticket_id', $ticketId)
            ->latest('detected_at')
            ->first();

        if (!$language) {
            return response()->json(['error' => 'No language detection found'], 404);
        }

        return response()->json([
            'detected_language' => $language->detected_language,
            'confidence' => $language->confidence,
            'requires_translation' => $language->requires_translation,
            'translation_status' => $language->translation_status,
            'translated_text' => $language->translated_text,
        ]);
    }

    /**
     * List routing rules with filtering
     *
     * @queryParam team_id integer Filter by team
     * @queryParam sentiment_trigger string Filter by sentiment (positive, negative, neutral)
     * @queryParam is_active boolean Filter active rules only
     * @queryParam per_page integer Results per page (default 15)
     */
    public function listRoutingRules(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewRoutingRules');

        $query = RoutingRule::with('team')
            ->when($request->query('team_id'), fn($q) => $q->where('team_id', $request->query('team_id')))
            ->when($request->query('sentiment_trigger'), fn($q) => $q->where('sentiment_trigger', $request->query('sentiment_trigger')))
            ->when($request->query('is_active'), fn($q) => $q->where('is_active', true));

        $rules = $query->orderBy('order')->paginate($request->query('per_page', 15));
        return $this->resourceCollection($rules);
    }

    /**
     * Create a new routing rule
     *
     * @bodyParam name string required
     * @bodyParam rule_type string required
     * @bodyParam sentiment_trigger string
     * @bodyParam target_queue string
     */
    public function createRoutingRule(Request $request): JsonResponse
    {
        $this->authorize('createRoutingRule');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rule_type' => 'required|string|in:sentiment_based,emotion_based,urgency_based',
            'sentiment_trigger' => 'string|in:positive,negative,neutral,mixed',
            'target_queue' => 'string|in:standard,priority,vip,escalation',
        ]);

        $rule = RoutingRule::create($validated);
        return response()->json($rule, 201);
    }

    /**
     * Get escalation prediction for a ticket
     *
     * @queryParam ticket_id integer Required. Ticket ID
     */
    public function getEscalationPrediction(Request $request): JsonResponse
    {
        $this->authorize('viewEscalationPredictions');

        $ticketId = $request->query('ticket_id');

        $prediction = EscalationPrediction::where('ticket_id', $ticketId)
            ->with('escalationModel')
            ->latest('predicted_at')
            ->first();

        if (!$prediction) {
            return response()->json(['error' => 'No escalation prediction found'], 404);
        }

        return response()->json([
            'urgency_score' => $prediction->urgency_score,
            'escalation_probability' => $prediction->escalation_probability,
            'confidence' => $prediction->confidence,
            'recommended_action' => $prediction->recommended_action,
            'escalation_level' => $prediction->escalation_level,
            'estimated_resolution_hours' => $prediction->estimated_resolution_hours,
            'contributing_factors' => $prediction->contributing_factors,
        ]);
    }

    /**
     * Get urgency factors for a ticket
     *
     * @queryParam ticket_id integer Required. Ticket ID
     */
    public function getUrgencyFactors(Request $request): JsonResponse
    {
        $this->authorize('viewUrgencyFactors');

        $ticketId = $request->query('ticket_id');

        $factors = UrgencyFactor::where('ticket_id', $ticketId)
            ->first();

        if (!$factors) {
            return response()->json(['error' => 'No urgency factors found'], 404);
        }

        return response()->json([
            'total_score' => $factors->total_urgency_score,
            'wait_time_hours' => $factors->wait_time_hours,
            'factors' => [
                'sentiment' => $factors->sentiment_factor,
                'complexity' => $factors->issue_complexity_factor,
                'agent_skill' => $factors->agent_skill_factor,
                'customer_vip' => $factors->customer_vip_factor,
                'sla_breach' => $factors->sla_breach_factor,
                'repeat_issue' => $factors->repeat_issue_factor,
                'channel' => $factors->channel_factor,
                'business_hours' => $factors->business_hours_factor,
            ],
        ]);
    }

    /**
     * List response templates with filtering
     *
     * @queryParam category string Filter by category
     * @queryParam language string Filter by language (default: en)
     * @queryParam tone string Filter by tone
     * @queryParam status string Filter by status (active, archived)
     * @queryParam per_page integer Results per page
     */
    public function listResponseTemplates(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewResponseTemplates');

        $query = ResponseTemplate::when($request->query('category'), fn($q) => $q->where('category', $request->query('category')))
            ->when($request->query('language'), fn($q) => $q->where('language', $request->query('language')))
            ->when($request->query('tone'), fn($q) => $q->where('tone', $request->query('tone')))
            ->when($request->query('status'), fn($q) => $q->where('status', $request->query('status')), fn($q) => $q->where('status', 'active'));

        $templates = $query->latest('created_at')->paginate($request->query('per_page', 15));
        return $this->resourceCollection($templates);
    }

    /**
     * Get response suggestions for a ticket
     *
     * @queryParam ticket_id integer Required. Ticket ID
     * @queryParam limit integer Limit results (default: 5)
     */
    public function getResponseSuggestions(Request $request): JsonResponse
    {
        $this->authorize('viewResponseSuggestions');

        $ticketId = $request->query('ticket_id');
        $limit = $request->query('limit', 5);

        $suggestions = ResponseSuggestion::where('ticket_id', $ticketId)
            ->with(['template', 'variant'])
            ->where('used', false)
            ->orderByDesc('relevance_score')
            ->take($limit)
            ->get();

        return response()->json([
            'suggestions' => $suggestions->map(fn($s) => [
                'id' => $s->id,
                'response' => $s->suggested_response,
                'reason' => $s->suggestion_reason,
                'relevance' => $s->relevance_score,
                'confidence' => $s->confidence,
                'template' => $s->template?->title,
            ]),
        ]);
    }

    /**
     * Record feedback on a suggestion
     *
     * @bodyParam suggestion_id integer required
     * @bodyParam accepted boolean required
     * @bodyParam rating float Rating 1-5
     * @bodyParam feedback_type string Type of feedback
     */
    public function recordSuggestionFeedback(Request $request): JsonResponse
    {
        $this->authorize('viewResponseSuggestions');

        $validated = $request->validate([
            'suggestion_id' => 'required|integer|exists:cs_response_suggestions,id',
            'accepted' => 'required|boolean',
            'rating' => 'nullable|numeric|min:1|max:5',
            'feedback_type' => 'string|in:helpful,partially_helpful,not_helpful',
        ]);

        $suggestion = ResponseSuggestion::findOrFail($validated['suggestion_id']);
        $suggestion->update([
            'accepted' => $validated['accepted'],
            'used' => true,
            'feedback_rating' => $validated['rating'] ?? null,
            'feedback_type' => $validated['feedback_type'] ?? null,
        ]);

        return response()->json(['message' => 'Feedback recorded successfully']);
    }

    /**
     * Get satisfaction prediction for a ticket
     *
     * @queryParam ticket_id integer Required. Ticket ID
     */
    public function getSatisfactionPrediction(Request $request): JsonResponse
    {
        $this->authorize('viewSatisfactionPredictions');

        $ticketId = $request->query('ticket_id');

        $prediction = SatisfactionPrediction::where('ticket_id', $ticketId)
            ->latest('predicted_at')
            ->first();

        if (!$prediction) {
            return response()->json(['error' => 'No satisfaction prediction found'], 404);
        }

        return response()->json([
            'predicted_score' => $prediction->predicted_satisfaction_score,
            'confidence' => $prediction->confidence,
            'category' => $prediction->satisfaction_category,
            'risk_factors' => $prediction->risk_factors,
            'improvement_suggestions' => $prediction->improvement_suggestions,
        ]);
    }

    /**
     * Get NPS prediction for a ticket
     *
     * @queryParam ticket_id integer Required. Ticket ID
     */
    public function getNPSPrediction(Request $request): JsonResponse
    {
        $this->authorize('viewNPSPredictions');

        $ticketId = $request->query('ticket_id');

        $nps = NPSPredictor::where('ticket_id', $ticketId)
            ->first();

        if (!$nps) {
            return response()->json(['error' => 'No NPS prediction found'], 404);
        }

        return response()->json([
            'predicted_nps' => $nps->predicted_nps_score,
            'likelihood' => $nps->promoter_likelihood,
            'probabilities' => [
                'promoter' => $nps->promoter_probability,
                'passive' => $nps->passive_probability,
                'detractor' => $nps->detractor_probability,
            ],
            'advocacy_score' => $nps->advocacy_score,
            'churn_risk' => $nps->churn_probability,
        ]);
    }

    /**
     * Get agent metrics for a period
     *
     * @queryParam agent_id integer Required. Agent user ID
     * @queryParam start_date date Required. Start date (Y-m-d)
     * @queryParam end_date date Required. End date (Y-m-d)
     */
    public function getAgentMetrics(Request $request): JsonResponse
    {
        $this->authorize('viewAgentMetrics');

        $validated = $request->validate([
            'agent_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $metrics = AgentMetric::where('agent_id', $validated['agent_id'])
            ->whereBetween('metric_date', [$validated['start_date'], $validated['end_date']])
            ->orderBy('metric_date', 'desc')
            ->get();

        if ($metrics->isEmpty()) {
            return response()->json(['error' => 'No metrics found for period'], 404);
        }

        return response()->json([
            'agent_id' => $validated['agent_id'],
            'period' => [
                'start' => $validated['start_date'],
                'end' => $validated['end_date'],
            ],
            'metrics' => $metrics->map(fn($m) => [
                'date' => $m->metric_date,
                'tickets_handled' => $m->tickets_handled,
                'avg_satisfaction' => $m->avg_satisfaction_rating,
                'first_contact_resolution_rate' => $m->first_contact_resolution_rate,
                'escalation_rate' => $m->escalation_rate,
                'overall_performance' => $m->overall_performance_score,
            ]),
        ]);
    }

    /**
     * Get agent performance trends
     *
     * @queryParam agent_id integer Required. Agent user ID
     * @queryParam period_type string monthly, quarterly, yearly
     * @queryParam limit integer Number of periods (default: 6)
     */
    public function getAgentPerformanceTrends(Request $request): JsonResponse
    {
        $this->authorize('viewAgentPerformanceTrends');

        $validated = $request->validate([
            'agent_id' => 'required|integer',
            'period_type' => 'string|in:daily,weekly,monthly,quarterly',
            'limit' => 'integer|min:1|max:24',
        ]);

        $trends = AgentPerformanceTrend::where('agent_id', $validated['agent_id'])
            ->where('period_type', $validated['period_type'] ?? 'monthly')
            ->orderBy('period_end_date', 'desc')
            ->take($validated['limit'] ?? 6)
            ->get();

        return response()->json([
            'trends' => $trends->map(fn($t) => [
                'period' => [$t->period_start_date, $t->period_end_date],
                'direction' => $t->performance_direction,
                'satisfaction_trend' => $t->satisfaction_trend,
                'quality_trend' => $t->quality_trend,
                'productivity_trend' => $t->productivity_trend,
                'improvements' => $t->top_improvements,
            ]),
        ]);
    }

    /**
     * Get agent skill analysis
     *
     * @queryParam agent_id integer Required. Agent user ID
     * @queryParam category string Filter by skill category
     */
    public function getAgentSkills(Request $request): JsonResponse
    {
        $this->authorize('viewAgentSkills');

        $validated = $request->validate([
            'agent_id' => 'required|integer',
            'category' => 'string',
        ]);

        $skills = AgentSkillAnalysis::where('agent_id', $validated['agent_id'])
            ->when($request->query('category'), fn($q) => $q->where('skill_category', $request->query('category')))
            ->orderByDesc('proficiency_score')
            ->get();

        return response()->json([
            'agent_id' => $validated['agent_id'],
            'skills' => $skills->map(fn($s) => [
                'name' => $s->skill_name,
                'category' => $s->skill_category,
                'level' => $s->skill_level,
                'proficiency' => $s->proficiency_score,
                'trend' => $s->proficiency_trend,
                'needs_training' => $s->needs_training,
                'tickets_handled' => $s->tickets_handled_for_skill,
                'avg_satisfaction' => $s->avg_satisfaction_for_skill,
            ]),
        ]);
    }

    /**
     * List coaching recommendations
     *
     * @queryParam agent_id integer Filter by agent
     * @queryParam status string Filter by status (pending, in_progress, completed)
     * @queryParam priority string Filter by priority
     * @queryParam per_page integer Results per page
     */
    public function listCoachingRecommendations(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewCoachingRecommendations');

        $query = AgentCoachingRecommendation::with(['agent', 'generatedBy'])
            ->when($request->query('agent_id'), fn($q) => $q->where('agent_id', $request->query('agent_id')))
            ->when($request->query('status'), fn($q) => $q->where('status', $request->query('status')))
            ->when($request->query('priority'), fn($q) => $q->where('priority', $request->query('priority')));

        $recommendations = $query->latest('created_at')->paginate($request->query('per_page', 15));
        return $this->resourceCollection($recommendations);
    }

    /**
     * Create coaching recommendation
     *
     * @bodyParam agent_id integer required
     * @bodyParam recommendation_type string required
     * @bodyParam description string required
     * @bodyParam priority string low, medium, high, critical
     */
    public function createCoachingRecommendation(Request $request): JsonResponse
    {
        $this->authorize('createCoachingRecommendation');

        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:users,id',
            'recommendation_type' => 'required|string',
            'description' => 'required|string',
            'priority' => 'string|in:low,medium,high,critical',
            'target_completion_date' => 'date',
        ]);

        $validated['generated_by'] = auth()->id();
        $recommendation = AgentCoachingRecommendation::create($validated);

        return response()->json($recommendation, 201);
    }

    /**
     * Get team benchmarking
     *
     * @queryParam team_id integer Required. Team ID
     */
    public function getTeamBenchmarking(Request $request): JsonResponse
    {
        $this->authorize('viewTeamBenchmarking');

        $teamId = $request->query('team_id');

        $benchmark = TeamBenchmarking::where('team_id', $teamId)
            ->latest('benchmark_date')
            ->first();

        if (!$benchmark) {
            return response()->json(['error' => 'No benchmarking data found'], 404);
        }

        return response()->json([
            'team_size' => $benchmark->team_size,
            'performance_rating' => $benchmark->team_performance_rating,
            'metrics' => [
                'avg_satisfaction' => $benchmark->avg_satisfaction_rating,
                'avg_resolution_time' => $benchmark->avg_resolution_time_minutes,
                'escalation_rate' => $benchmark->avg_escalation_rate,
                'first_contact_resolution_rate' => $benchmark->avg_first_contact_resolution_rate,
                'nps_score' => $benchmark->avg_nps_score,
            ],
            'performance_gap' => $benchmark->getPerformanceGap(),
            'strengths' => $benchmark->strengths,
            'improvement_areas' => $benchmark->improvement_areas,
            'trend' => $benchmark->team_trend,
        ]);
    }

    /**
     * List performance goals
     *
     * @queryParam agent_id integer Filter by agent
     * @queryParam status string Filter by status
     * @queryParam category string Filter by category
     * @queryParam per_page integer Results per page
     */
    public function listPerformanceGoals(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewPerformanceGoals');

        $query = PerformanceGoal::with(['agent', 'createdBy'])
            ->when($request->query('agent_id'), fn($q) => $q->where('agent_id', $request->query('agent_id')))
            ->when($request->query('status'), fn($q) => $q->where('status', $request->query('status')))
            ->when($request->query('category'), fn($q) => $q->where('goal_category', $request->query('category')));

        $goals = $query->latest('created_at')->paginate($request->query('per_page', 15));
        return $this->resourceCollection($goals);
    }

    /**
     * Create performance goal
     *
     * @bodyParam agent_id integer required
     * @bodyParam goal_description string required
     * @bodyParam metric_name string required
     * @bodyParam target_value integer required
     * @bodyParam end_date date required
     */
    public function createPerformanceGoal(Request $request): JsonResponse
    {
        $this->authorize('createPerformanceGoal');

        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:users,id',
            'goal_description' => 'required|string',
            'metric_name' => 'required|string',
            'target_value' => 'required|integer',
            'end_date' => 'required|date',
            'goal_category' => 'string',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['start_date'] = now()->toDateString();
        $goal = PerformanceGoal::create($validated);

        return response()->json($goal, 201);
    }

    /**
     * Update performance goal progress
     *
     * @bodyParam goal_id integer required
     * @bodyParam current_value integer Current progress value
     * @bodyParam status string Goal status
     */
    public function updateGoalProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goal_id' => 'required|integer|exists:cs_performance_goals,id',
            'current_value' => 'integer',
            'status' => 'string|in:active,on_track,at_risk,completed',
        ]);

        $goal = PerformanceGoal::findOrFail($validated['goal_id']);
        $this->authorize('updatePerformanceGoal', $goal);

        if (isset($validated['current_value']) && isset($goal->target_value)) {
            $progress = $validated['current_value'] / $goal->target_value;
            $goal->updateProgress($progress);
        }

        if ($validated['status']) {
            $goal->update(['status' => $validated['status']]);
        }

        return response()->json(['message' => 'Goal progress updated']);
    }

    // HELPER METHODS
    protected function resourceCollection($collection): AnonymousResourceCollection
    {
        return JsonResource::collection($collection);
    }
}
