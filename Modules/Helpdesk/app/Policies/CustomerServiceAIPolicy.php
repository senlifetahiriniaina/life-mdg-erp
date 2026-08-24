<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerServiceAIPolicy
{
    // SENTIMENT ANALYSIS PERMISSIONS
    public function viewSentimentAnalysis(User $user): bool
    {
        return $user->can('helpdesk.sentiment.view');
    }

    public function manageSentimentModels(User $user): bool
    {
        return $user->can('helpdesk.sentiment.manage');
    }

    public function deploySentimentModel(User $user): bool
    {
        return $user->can('helpdesk.sentiment.deploy');
    }

    public function viewEmotionAnalysis(User $user): bool
    {
        return $user->can('helpdesk.emotion.view');
    }

    public function viewLanguageDetection(User $user): bool
    {
        return $user->can('helpdesk.language.view');
    }

    public function manageLanguageDetection(User $user): bool
    {
        return $user->can('helpdesk.language.manage');
    }

    // ROUTING RULES PERMISSIONS
    public function viewRoutingRules(User $user): bool
    {
        return $user->can('helpdesk.routing.view');
    }

    public function createRoutingRule(User $user): bool
    {
        return $user->can('helpdesk.routing.create');
    }

    public function updateRoutingRule(User $user, Model $model): bool
    {
        return $user->can('helpdesk.routing.update');
    }

    public function deleteRoutingRule(User $user, Model $model): bool
    {
        return $user->can('helpdesk.routing.delete');
    }

    // PREDICTIVE ESCALATION PERMISSIONS
    public function viewEscalationPredictions(User $user): bool
    {
        return $user->can('helpdesk.escalation.view');
    }

    public function manageEscalationModels(User $user): bool
    {
        return $user->can('helpdesk.escalation.manage');
    }

    public function deployEscalationModel(User $user): bool
    {
        return $user->can('helpdesk.escalation.deploy');
    }

    public function viewUrgencyFactors(User $user): bool
    {
        return $user->can('helpdesk.urgency.view');
    }

    public function executeEscalation(User $user): bool
    {
        return $user->can('helpdesk.escalation.execute');
    }

    // CANNED RESPONSE PERMISSIONS
    public function viewResponseTemplates(User $user): bool
    {
        return $user->can('helpdesk.response.view');
    }

    public function createResponseTemplate(User $user): bool
    {
        return $user->can('helpdesk.response.create');
    }

    public function updateResponseTemplate(User $user, Model $model): bool
    {
        return $user->can('helpdesk.response.update');
    }

    public function deleteResponseTemplate(User $user, Model $model): bool
    {
        return $user->can('helpdesk.response.delete');
    }

    public function viewResponseVariants(User $user): bool
    {
        return $user->can('helpdesk.response.variants.view');
    }

    public function generateAIVariants(User $user): bool
    {
        return $user->can('helpdesk.response.variants.generate');
    }

    public function viewResponseSuggestions(User $user): bool
    {
        return $user->can('helpdesk.response.suggestions.view');
    }

    public function viewResponsePerformance(User $user): bool
    {
        return $user->can('helpdesk.response.performance.view');
    }

    // SATISFACTION PREDICTION PERMISSIONS
    public function viewSatisfactionPredictions(User $user): bool
    {
        return $user->can('helpdesk.satisfaction.view');
    }

    public function manageSatisfactionModels(User $user): bool
    {
        return $user->can('helpdesk.satisfaction.manage');
    }

    public function deploySatisfactionModel(User $user): bool
    {
        return $user->can('helpdesk.satisfaction.deploy');
    }

    public function viewNPSPredictions(User $user): bool
    {
        return $user->can('helpdesk.nps.view');
    }

    // AGENT PERFORMANCE PERMISSIONS
    public function viewAgentMetrics(User $user): bool
    {
        return $user->can('helpdesk.agent-metrics.view');
    }

    public function viewTeamMetrics(User $user): bool
    {
        return $user->can('helpdesk.team-metrics.view');
    }

    public function viewAgentPerformanceTrends(User $user): bool
    {
        return $user->can('helpdesk.agent-trends.view');
    }

    public function viewAgentSkills(User $user): bool
    {
        return $user->can('helpdesk.agent-skills.view');
    }

    public function manageAgentSkills(User $user): bool
    {
        return $user->can('helpdesk.agent-skills.manage');
    }

    public function viewCoachingRecommendations(User $user): bool
    {
        return $user->can('helpdesk.coaching.view');
    }

    public function createCoachingRecommendation(User $user): bool
    {
        return $user->can('helpdesk.coaching.create');
    }

    public function updateCoachingRecommendation(User $user, Model $model): bool
    {
        return $user->can('helpdesk.coaching.update');
    }

    public function viewTeamBenchmarking(User $user): bool
    {
        return $user->can('helpdesk.benchmarking.view');
    }

    public function generateBenchmark(User $user): bool
    {
        return $user->can('helpdesk.benchmarking.generate');
    }

    public function viewPerformanceGoals(User $user): bool
    {
        return $user->can('helpdesk.goals.view');
    }

    public function createPerformanceGoal(User $user): bool
    {
        return $user->can('helpdesk.goals.create');
    }

    public function updatePerformanceGoal(User $user, Model $model): bool
    {
        return $user->can('helpdesk.goals.update');
    }

    public function deletePerformanceGoal(User $user, Model $model): bool
    {
        return $user->can('helpdesk.goals.delete');
    }

    // EXPORT AND REPORTING
    public function exportAIData(User $user): bool
    {
        return $user->can('helpdesk.ai.export');
    }

    public function viewAIReports(User $user): bool
    {
        return $user->can('helpdesk.ai.reports');
    }

    public function configureAIModels(User $user): bool
    {
        return $user->can('helpdesk.ai.configure');
    }
}
