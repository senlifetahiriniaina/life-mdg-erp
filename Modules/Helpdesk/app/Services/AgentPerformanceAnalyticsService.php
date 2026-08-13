<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Carbon;
use Modules\AuditLog\Models\AuditLog;
use Modules\Helpdesk\Models\Ticket;

/**
 * AgentPerformanceAnalyticsService - Advanced performance analytics
 *
 * Provides comprehensive agent performance metrics:
 * - Resolution time, FCR rate, CSAT, sentiment improvement
 * - Escalation frequency and SLA adherence
 * - Performance trends (daily, weekly, monthly, quarterly)
 * - Skill analysis and proficiency by category
 * - Peer benchmarking and ranking
 * - Coaching recommendations and goal setting
 * - Workload balancing and quality insights
 * - Burnout risk detection and ramp-up analysis
 */
class AgentPerformanceAnalyticsService
{
    private const METRIC_WEIGHTS = [
        'resolution_time' => 0.2,
        'fcr_rate' => 0.25,
        'csat_score' => 0.25,
        'sentiment_improvement' => 0.15,
        'sla_adherence' => 0.15,
    ];

    /**
     * Calculate comprehensive agent metrics.
     *
     * @return array<string, mixed>
     */
    public function calculateAgentMetrics(int $agentId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $agent = \Modules\Core\Models\User::find($agentId);
        if (!$agent) {
            return [];
        }

        $from ??= now()->subMonths(1);
        $to ??= now();

        $tickets = Ticket::where('assignee_id', $agentId)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return [
            'agent_id' => $agentId,
            'agent_name' => $agent->name,
            'period_start' => $from,
            'period_end' => $to,
            'metrics' => [
                'average_resolution_time' => $this->calculateAverageResolutionTime($tickets),
                'first_contact_resolution_rate' => $this->calculateFCRRate($tickets),
                'customer_satisfaction_score' => $this->calculateCSAT($tickets),
                'sentiment_improvement_rate' => $this->calculateSentimentImprovement($tickets),
                'escalation_frequency' => $this->calculateEscalationFrequency($tickets),
                'sla_adherence_rate' => $this->calculateSLAAdherence($tickets),
                'response_quality_score' => $this->calculateResponseQuality($tickets),
            ],
            'overall_performance_score' => $this->calculateOverallScore($tickets),
            'total_tickets_handled' => count($tickets),
            'resolved_tickets' => count($tickets->where('status', 'resolved')),
        ];
    }

    /**
     * Generate performance trends (daily, weekly, monthly, quarterly).
     *
     * @return array<string, array<string, mixed>>
     */
    public function generatePerformanceTrends(int $agentId, string $granularity = 'daily'): array
    {
        $agent = \Modules\Core\Models\User::find($agentId);
        if (!$agent) {
            return [];
        }

        $trends = [];

        switch ($granularity) {
            case 'daily':
                $trends = $this->getDailyTrends($agentId);
                break;
            case 'weekly':
                $trends = $this->getWeeklyTrends($agentId);
                break;
            case 'monthly':
                $trends = $this->getMonthlyTrends($agentId);
                break;
            case 'quarterly':
                $trends = $this->getQuarterlyTrends($agentId);
                break;
        }

        return $trends;
    }

    /**
     * Analyze skill proficiency by ticket category.
     *
     * @return array<string, array<string, mixed>>
     */
    public function analyzeSkillProficiency(int $agentId): array
    {
        $tickets = Ticket::where('assignee_id', $agentId)->get()->groupBy('category');

        $skills = [];

        foreach ($tickets as $category => $categoryTickets) {
            $resolved = $categoryTickets->where('status', 'resolved')->count();
            $total = count($categoryTickets);
            $resolutionRate = $total > 0 ? ($resolved / $total) * 100 : 0;

            $avgTime = 0;
            if ($resolved > 0) {
                $times = $categoryTickets
                    ->filter(fn ($t) => $t->status === 'resolved')
                    ->map(fn ($t) => $t->created_at->diffInHours($t->resolved_at))
                    ->toArray();
                $avgTime = count($times) > 0 ? array_sum($times) / count($times) : 0;
            }

            $skills[$category] = [
                'category' => $category,
                'total_tickets' => $total,
                'resolved_tickets' => $resolved,
                'resolution_rate' => round($resolutionRate, 2),
                'average_resolution_time' => round($avgTime, 1),
                'proficiency_level' => $this->proficiencyLevel($resolutionRate),
            ];
        }

        return $skills;
    }

    /**
     * Identify expertise areas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function identifyExpertiseAreas(int $agentId): array
    {
        $skills = $this->analyzeSkillProficiency($agentId);

        $expertise = array_filter($skills, fn ($s) => $s['proficiency_level'] === 'expert');
        usort($expertise, fn ($a, $b) => $b['resolution_rate'] <=> $a['resolution_rate']);

        return array_slice($expertise, 0, 5);
    }

    /**
     * Identify skill gaps.
     *
     * @return array<int, array<string, mixed>>
     */
    public function identifySkillGaps(int $agentId): array
    {
        $skills = $this->analyzeSkillProficiency($agentId);

        $gaps = array_filter($skills, fn ($s) => $s['proficiency_level'] === 'beginner' || $s['resolution_rate'] < 50);
        usort($gaps, fn ($a, $b) => $a['resolution_rate'] <=> $b['resolution_rate']);

        return array_slice($gaps, 0, 5);
    }

    /**
     * Peer benchmarking - compare with team average.
     *
     * @return array<string, mixed>
     */
    public function benchmarkAgainstTeam(int $agentId): array
    {
        $agentMetrics = $this->calculateAgentMetrics($agentId);
        $teamMetrics = $this->calculateTeamAverageMetrics();

        $comparison = [
            'agent_id' => $agentId,
            'metrics_comparison' => [],
        ];

        foreach ($agentMetrics['metrics'] as $metric => $value) {
            $teamValue = $teamMetrics['metrics'][$metric] ?? 0;
            $difference = $value - $teamValue;
            $percentageDifference = $teamValue > 0 ? round(($difference / $teamValue) * 100, 2) : 0;

            $comparison['metrics_comparison'][$metric] = [
                'agent_value' => $value,
                'team_average' => $teamValue,
                'difference' => round($difference, 2),
                'percentage_difference' => $percentageDifference,
                'vs_team' => $difference > 0 ? 'above' : 'below',
            ];
        }

        return $comparison;
    }

    /**
     * Compare with top performers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function compareWithTopPerformers(int $agentId): array
    {
        $allAgents = \Modules\Core\Models\User::where('role', 'support_agent')->get();
        $agentMetrics = $this->calculateAgentMetrics($agentId);
        $overallScore = $agentMetrics['overall_performance_score'];

        $topPerformers = [];

        foreach ($allAgents as $agent) {
            $metrics = $this->calculateAgentMetrics($agent->id);
            $topPerformers[] = [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'performance_score' => $metrics['overall_performance_score'],
                'tickets_handled' => count($metrics),
            ];
        }

        usort($topPerformers, fn ($a, $b) => $b['performance_score'] <=> $a['performance_score']);

        $ranking = array_search(
            $agentId,
            array_column($topPerformers, 'agent_id'),
            true
        );

        return [
            'your_ranking' => ($ranking ?? count($topPerformers)) + 1,
            'total_agents' => count($topPerformers),
            'your_score' => $overallScore,
            'top_performers' => array_slice($topPerformers, 0, 5),
        ];
    }

    /**
     * Generate agent performance ranking.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generatePerformanceRanking(): array
    {
        $agents = \Modules\Core\Models\User::where('role', 'support_agent')->get();

        $rankings = [];

        foreach ($agents as $agent) {
            $metrics = $this->calculateAgentMetrics($agent->id);
            $rankings[] = [
                'rank' => 0,
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'performance_score' => $metrics['overall_performance_score'],
                'metrics' => $metrics['metrics'],
            ];
        }

        usort($rankings, fn ($a, $b) => $b['performance_score'] <=> $a['performance_score']);

        // Add rank numbers
        foreach ($rankings as &$rank) {
            $rank['rank'] = array_search($rank['agent_id'], array_column($rankings, 'agent_id'), true) + 1;
        }

        return $rankings;
    }

    /**
     * Generate coaching recommendations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateCoachingRecommendations(int $agentId): array
    {
        $metrics = $this->calculateAgentMetrics($agentId);
        $skillGaps = $this->identifySkillGaps($agentId);
        $benchmarking = $this->benchmarkAgainstTeam($agentId);

        $recommendations = [];

        // Low resolution time - needs efficiency training
        if ($metrics['metrics']['average_resolution_time'] > 24) {
            $recommendations[] = [
                'area' => 'efficiency',
                'recommendation' => 'Resolution time improvement training',
                'details' => 'Current average: ' . round($metrics['metrics']['average_resolution_time'], 1) . ' hours',
                'priority' => 'high',
            ];
        }

        // Low FCR rate - needs better diagnosis
        if ($metrics['metrics']['first_contact_resolution_rate'] < 50) {
            $recommendations[] = [
                'area' => 'first_contact_resolution',
                'recommendation' => 'Problem diagnosis and solution finding skills',
                'details' => 'Current FCR rate: ' . round($metrics['metrics']['first_contact_resolution_rate'], 1) . '%',
                'priority' => 'high',
            ];
        }

        // Low customer satisfaction
        if ($metrics['metrics']['customer_satisfaction_score'] < 3.5) {
            $recommendations[] = [
                'area' => 'customer_satisfaction',
                'recommendation' => 'Customer communication and empathy training',
                'details' => 'Current CSAT: ' . round($metrics['metrics']['customer_satisfaction_score'], 1) . '/5',
                'priority' => 'high',
            ];
        }

        // Skill gaps in categories
        foreach ($skillGaps as $gap) {
            $recommendations[] = [
                'area' => 'technical_skill',
                'recommendation' => "Category training: {$gap['category']}",
                'details' => "Resolution rate: " . round($gap['resolution_rate'], 1) . "%",
                'priority' => 'medium',
            ];
        }

        return $recommendations;
    }

    /**
     * Goal setting and tracking.
     *
     * @return array<string, mixed>
     */
    public function setSmartGoal(int $agentId, array $goalConfig): array
    {
        $currentMetrics = $this->calculateAgentMetrics($agentId);

        $goal = [
            'agent_id' => $agentId,
            'metric' => $goalConfig['metric'],
            'current_value' => $currentMetrics['metrics'][$goalConfig['metric']] ?? 0,
            'target_value' => $goalConfig['target'],
            'deadline' => $goalConfig['deadline'] ?? now()->addMonths(3),
            'status' => 'active',
            'created_at' => now(),
        ];

        return $goal;
    }

    /**
     * Track goal progress.
     *
     * @return array<string, mixed>
     */
    public function trackGoalProgress(int $agentId, int $goalId): array
    {
        // In production, retrieve goal from database
        $metrics = $this->calculateAgentMetrics($agentId);

        return [
            'agent_id' => $agentId,
            'goal_id' => $goalId,
            'current_metrics' => $metrics['metrics'],
            'progress_percentage' => 65, // Example
            'on_track' => true,
        ];
    }

    /**
     * Workload balancing analysis.
     *
     * @return array<string, mixed>
     */
    public function analyzeWorkloadBalance(): array
    {
        $agents = \Modules\Core\Models\User::where('role', 'support_agent')->get();

        $workloads = [];
        $totalTickets = 0;

        foreach ($agents as $agent) {
            $activeTickets = Ticket::where('assignee_id', $agent->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->count();

            $workloads[$agent->id] = [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'active_tickets' => $activeTickets,
            ];

            $totalTickets += $activeTickets;
        }

        $averageWorkload = count($agents) > 0 ? $totalTickets / count($agents) : 0;

        $imbalances = [];
        foreach ($workloads as $agentId => $workload) {
            $difference = abs($workload['active_tickets'] - $averageWorkload);
            if ($difference > ($averageWorkload * 0.3)) { // 30% threshold
                $imbalances[$agentId] = [
                    'agent_id' => $agentId,
                    'agent_name' => $workload['agent_name'],
                    'current_load' => $workload['active_tickets'],
                    'average_load' => round($averageWorkload, 1),
                    'imbalance_status' => $workload['active_tickets'] > $averageWorkload ? 'overloaded' : 'underloaded',
                ];
            }
        }

        return [
            'average_workload' => round($averageWorkload, 1),
            'total_agents' => count($agents),
            'total_active_tickets' => $totalTickets,
            'workloads' => $workloads,
            'imbalances' => $imbalances,
        ];
    }

    /**
     * Quality assurance insights.
     *
     * @return array<string, mixed>
     */
    public function generateQualityInsights(int $agentId): array
    {
        $metrics = $this->calculateAgentMetrics($agentId);

        $insights = [
            'agent_id' => $agentId,
            'response_quality' => [
                'score' => $metrics['metrics']['response_quality_score'],
                'status' => $metrics['metrics']['response_quality_score'] > 4 ? 'excellent' : 'needs_improvement',
            ],
            'sla_compliance' => [
                'rate' => $metrics['metrics']['sla_adherence_rate'],
                'status' => $metrics['metrics']['sla_adherence_rate'] >= 95 ? 'compliant' : 'at_risk',
            ],
            'customer_satisfaction' => [
                'score' => $metrics['metrics']['customer_satisfaction_score'],
                'trend' => 'stable', // Would calculate from trends
            ],
            'overall_quality_score' => $metrics['overall_performance_score'],
        ];

        return $insights;
    }

    /**
     * Burnout risk detection.
     *
     * @return array<string, mixed>
     */
    public function detectBurnoutRisk(int $agentId): array
    {
        $metrics = $this->calculateAgentMetrics($agentId);
        $trends = $this->generatePerformanceTrends($agentId, 'weekly');

        $burnoutScore = 0.0;
        $riskFactors = [];

        // Declining metrics = burnout risk
        foreach ($trends as $period) {
            if ($period['trend'] === 'declining') {
                $burnoutScore += 0.2;
                $riskFactors[] = 'Performance declining';
            }
        }

        // High workload
        $workload = $this->analyzeWorkloadBalance();
        $agentWorkload = $workload['workloads'][$agentId] ?? [];
        if ($agentWorkload['active_tickets'] > ($workload['average_workload'] * 1.5)) {
            $burnoutScore += 0.2;
            $riskFactors[] = 'Overloaded with tickets';
        }

        // Low satisfaction
        if ($metrics['metrics']['customer_satisfaction_score'] < 3) {
            $burnoutScore += 0.15;
            $riskFactors[] = 'Low customer satisfaction';
        }

        // Low FCR rate
        if ($metrics['metrics']['first_contact_resolution_rate'] < 40) {
            $burnoutScore += 0.15;
            $riskFactors[] = 'Low first contact resolution rate';
        }

        $riskLevel = match (true) {
            $burnoutScore >= 0.7 => 'critical',
            $burnoutScore >= 0.5 => 'high',
            $burnoutScore >= 0.3 => 'moderate',
            default => 'low',
        };

        return [
            'agent_id' => $agentId,
            'burnout_risk_score' => round($burnoutScore, 2),
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'recommendations' => $this->getBurnoutRecommendations($riskLevel),
        ];
    }

    /**
     * Learning curve analysis (ramp-up time).
     *
     * @return array<string, mixed>
     */
    public function analyzeLearningCurve(int $agentId): array
    {
        $agent = \Modules\Core\Models\User::find($agentId);
        if (!$agent) {
            return [];
        }

        $monthlyMetrics = [];
        for ($i = 0; $i < 6; $i++) {
            $from = now()->subMonths($i + 1)->startOfMonth();
            $to = now()->subMonths($i)->endOfMonth();

            $metrics = $this->calculateAgentMetrics($agentId, $from, $to);
            $monthlyMetrics[] = [
                'month' => $from->format('Y-m'),
                'fcr_rate' => $metrics['metrics']['first_contact_resolution_rate'] ?? 0,
                'avg_resolution_time' => $metrics['metrics']['average_resolution_time'] ?? 0,
                'csat' => $metrics['metrics']['customer_satisfaction_score'] ?? 0,
            ];
        }

        $improvement = $this->calculateImprovementRate($monthlyMetrics);

        return [
            'agent_id' => $agentId,
            'hire_date' => $agent->created_at,
            'months_employed' => $agent->created_at->diffInMonths(now()),
            'monthly_metrics' => $monthlyMetrics,
            'improvement_rate' => $improvement,
            'ramp_up_status' => $this->assessRampUpStatus($improvement, $agent->created_at),
        ];
    }

    /**
     * Batch calculate metrics for all agents.
     *
     * @return array<int, array<string, mixed>>
     */
    public function batchCalculateAgentMetrics(): array
    {
        $agents = \Modules\Core\Models\User::where('role', 'support_agent')->get();

        $results = [];

        foreach ($agents as $agent) {
            try {
                $results[$agent->id] = $this->calculateAgentMetrics($agent->id);
            } catch (\Throwable $e) {
                $this->logError('metrics_calculation_failed', $agent->id, $e);
                $results[$agent->id] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    // ==================== Private Helper Methods ====================

    /**
     * Calculate average resolution time.
     */
    private function calculateAverageResolutionTime($tickets): float
    {
        $resolvedTickets = $tickets->filter(fn ($t) => $t->resolved_at || $t->closed_at);

        if ($resolvedTickets->isEmpty()) {
            return 0.0;
        }

        $times = $resolvedTickets->map(function ($t) {
            $resolveTime = $t->resolved_at ?? $t->closed_at;
            return $t->created_at->diffInHours($resolveTime);
        });

        return round($times->average(), 1);
    }

    /**
     * Calculate first contact resolution rate.
     */
    private function calculateFCRRate($tickets): float
    {
        if ($tickets->isEmpty()) {
            return 0.0;
        }

        $fcr = $tickets->filter(fn ($t) => $t->messages()->count() <= 2)->count();

        return round(($fcr / count($tickets)) * 100, 2);
    }

    /**
     * Calculate customer satisfaction.
     */
    private function calculateCSAT($tickets): float
    {
        $withScores = $tickets->filter(fn ($t) => $t->satisfaction_score !== null);

        if ($withScores->isEmpty()) {
            return 0.0;
        }

        return round($withScores->avg('satisfaction_score'), 2);
    }

    /**
     * Calculate sentiment improvement rate.
     */
    private function calculateSentimentImprovement($tickets): float
    {
        $sentimentService = app(SentimentAnalysisService::class);
        $improved = 0;

        foreach ($tickets as $ticket) {
            $improvement = $sentimentService->trackSentimentImprovement($ticket);
            if ($improvement['improvement_detected']) {
                $improved++;
            }
        }

        return count($tickets) > 0 ? round(($improved / count($tickets)) * 100, 2) : 0;
    }

    /**
     * Calculate escalation frequency.
     */
    private function calculateEscalationFrequency($tickets): float
    {
        $escalated = $tickets->filter(fn ($t) => $t->escalation_level !== null)->count();

        return count($tickets) > 0 ? round(($escalated / count($tickets)) * 100, 2) : 0;
    }

    /**
     * Calculate SLA adherence rate.
     */
    private function calculateSLAAdherence($tickets): float
    {
        $withSLA = $tickets->filter(fn ($t) => $t->sla_due_at !== null);

        if ($withSLA->isEmpty()) {
            return 100.0;
        }

        $compliant = $withSLA->filter(fn ($t) => !$t->sla_breached)->count();

        return round(($compliant / count($withSLA)) * 100, 2);
    }

    /**
     * Calculate response quality score.
     */
    private function calculateResponseQuality($tickets): float
    {
        if ($tickets->isEmpty()) {
            return 0.0;
        }

        $qualityScores = [];

        foreach ($tickets as $ticket) {
            $responses = $ticket->responses()->get();
            if ($responses->isNotEmpty()) {
                $avgLength = $responses->avg(fn ($r) => strlen($r->content));
                $qualityScores[] = min($avgLength / 500, 1.0); // Normalize by 500 chars
            }
        }

        return count($qualityScores) > 0 ? round(array_sum($qualityScores) / count($qualityScores), 2) : 0;
    }

    /**
     * Calculate overall performance score.
     */
    private function calculateOverallScore($tickets): float
    {
        $metrics = [
            $this->calculateAverageResolutionTime($tickets) / 24, // Normalize to 0-1
            $this->calculateFCRRate($tickets) / 100,
            $this->calculateCSAT($tickets) / 5,
            $this->calculateSentimentImprovement($tickets) / 100,
            $this->calculateSLAAdherence($tickets) / 100,
        ];

        $weighted = 0.0;
        $weights = array_values(self::METRIC_WEIGHTS);

        foreach ($metrics as $i => $metric) {
            $weighted += $metric * ($weights[$i] ?? 0.2);
        }

        return round($weighted * 100, 2);
    }

    /**
     * Get daily performance trends.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDailyTrends(int $agentId): array
    {
        $trends = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $tickets = Ticket::where('assignee_id', $agentId)
                ->whereDate('created_at', $date)
                ->get();

            if ($tickets->isEmpty()) {
                continue;
            }

            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'fcr_rate' => $this->calculateFCRRate($tickets),
                'avg_resolution_time' => $this->calculateAverageResolutionTime($tickets),
                'csat' => $this->calculateCSAT($tickets),
            ];
        }

        return $trends;
    }

    /**
     * Get weekly performance trends.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getWeeklyTrends(int $agentId): array
    {
        $trends = [];

        for ($i = 11; $i >= 0; $i--) {
            $startDate = now()->subWeeks($i + 1)->startOfWeek();
            $endDate = now()->subWeeks($i)->endOfWeek();

            $tickets = Ticket::where('assignee_id', $agentId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            if ($tickets->isEmpty()) {
                continue;
            }

            $trends[] = [
                'week' => $startDate->format('Y-W'),
                'fcr_rate' => $this->calculateFCRRate($tickets),
                'avg_resolution_time' => $this->calculateAverageResolutionTime($tickets),
                'csat' => $this->calculateCSAT($tickets),
            ];
        }

        return $trends;
    }

    /**
     * Get monthly performance trends.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getMonthlyTrends(int $agentId): array
    {
        $trends = [];

        for ($i = 11; $i >= 0; $i--) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();

            $tickets = Ticket::where('assignee_id', $agentId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            if ($tickets->isEmpty()) {
                continue;
            }

            $trends[] = [
                'month' => $startDate->format('Y-m'),
                'fcr_rate' => $this->calculateFCRRate($tickets),
                'avg_resolution_time' => $this->calculateAverageResolutionTime($tickets),
                'csat' => $this->calculateCSAT($tickets),
            ];
        }

        return $trends;
    }

    /**
     * Get quarterly performance trends.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getQuarterlyTrends(int $agentId): array
    {
        $trends = [];

        for ($i = 3; $i >= 0; $i--) {
            $startDate = now()->subQuarters($i + 1)->startOfQuarter();
            $endDate = now()->subQuarters($i)->endOfQuarter();

            $tickets = Ticket::where('assignee_id', $agentId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            if ($tickets->isEmpty()) {
                continue;
            }

            $trends[] = [
                'quarter' => $startDate->format('Y-\QQ'),
                'fcr_rate' => $this->calculateFCRRate($tickets),
                'avg_resolution_time' => $this->calculateAverageResolutionTime($tickets),
                'csat' => $this->calculateCSAT($tickets),
            ];
        }

        return $trends;
    }

    /**
     * Determine proficiency level.
     */
    private function proficiencyLevel(float $resolutionRate): string
    {
        if ($resolutionRate >= 80) {
            return 'expert';
        } elseif ($resolutionRate >= 60) {
            return 'intermediate';
        } elseif ($resolutionRate >= 40) {
            return 'novice';
        }

        return 'beginner';
    }

    /**
     * Calculate team average metrics.
     *
     * @return array<string, mixed>
     */
    private function calculateTeamAverageMetrics(): array
    {
        $agents = \Modules\Core\Models\User::where('role', 'support_agent')->get();

        $allTickets = Ticket::whereIn('assignee_id', $agents->pluck('id'))->get();

        return [
            'metrics' => [
                'average_resolution_time' => $this->calculateAverageResolutionTime($allTickets),
                'first_contact_resolution_rate' => $this->calculateFCRRate($allTickets),
                'customer_satisfaction_score' => $this->calculateCSAT($allTickets),
                'sentiment_improvement_rate' => $this->calculateSentimentImprovement($allTickets),
                'escalation_frequency' => $this->calculateEscalationFrequency($allTickets),
                'sla_adherence_rate' => $this->calculateSLAAdherence($allTickets),
            ],
        ];
    }

    /**
     * Get burnout recommendations.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getBurnoutRecommendations(string $riskLevel): array
    {
        $recommendations = match ($riskLevel) {
            'critical' => [
                ['action' => 'Immediate manager discussion', 'urgency' => 'critical'],
                ['action' => 'Consider temporary workload reduction', 'urgency' => 'high'],
                ['action' => 'Explore wellness programs', 'urgency' => 'high'],
            ],
            'high' => [
                ['action' => 'Weekly check-ins with manager', 'urgency' => 'high'],
                ['action' => 'Skill training to improve efficiency', 'urgency' => 'medium'],
                ['action' => 'Peer mentoring assignment', 'urgency' => 'medium'],
            ],
            'moderate' => [
                ['action' => 'Monthly performance review', 'urgency' => 'medium'],
                ['action' => 'Development plan discussion', 'urgency' => 'low'],
            ],
            default => [
                ['action' => 'Continue standard monitoring', 'urgency' => 'low'],
            ],
        };

        return $recommendations;
    }

    /**
     * Calculate improvement rate from metrics.
     *
     * @param array<int, array<string, mixed>> $monthlyMetrics
     */
    private function calculateImprovementRate(array $monthlyMetrics): float
    {
        if (count($monthlyMetrics) < 2) {
            return 0.0;
        }

        $newest = $monthlyMetrics[0];
        $oldest = end($monthlyMetrics);

        $fcrImprovement = ($newest['fcr_rate'] - $oldest['fcr_rate']) / max($oldest['fcr_rate'], 1);
        $timeImprovement = ($oldest['avg_resolution_time'] - $newest['avg_resolution_time']) / max($oldest['avg_resolution_time'], 1);
        $csatImprovement = ($newest['csat'] - $oldest['csat']) / max($oldest['csat'], 1);

        $avg = ($fcrImprovement + $timeImprovement + $csatImprovement) / 3;

        return round($avg * 100, 2);
    }

    /**
     * Assess ramp-up status.
     */
    private function assessRampUpStatus(float $improvementRate, \DateTimeInterface $hireDate): string
    {
        $monthsEmployed = $hireDate->diff(now())->m;

        if ($monthsEmployed < 3) {
            return 'ramping_up';
        } elseif ($improvementRate > 10) {
            return 'rapid_improvement';
        } elseif ($improvementRate > 0) {
            return 'steady_improvement';
        } elseif ($improvementRate < -10) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Calculate trend from metrics.
     */
    private function calculateMetricTrend($metrics): string
    {
        if (count($metrics) < 2) {
            return 'insufficient_data';
        }

        $recent = array_slice($metrics, -5);
        $older = array_slice($metrics, 0, 5);

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);

        $change = $recentAvg - $olderAvg;

        if ($change > 5) {
            return 'improving';
        } elseif ($change < -5) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Log error for performance analytics.
     */
    private function logError(string $action, int $agentId, \Throwable $e): void
    {
        AuditLog::create([
            'action' => $action,
            'model_type' => 'Agent',
            'model_id' => $agentId,
            'changes' => ['error' => $e->getMessage()],
            'user_id' => null,
        ]);
    }
}
