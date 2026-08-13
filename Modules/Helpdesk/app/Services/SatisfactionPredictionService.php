<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\AuditLog\Models\AuditLog;
use Modules\Helpdesk\Models\Ticket;
use Modules\Shared\Services\PersonalizationFramework;

/**
 * SatisfactionPredictionService - Customer satisfaction ML prediction
 *
 * Provides satisfaction prediction and tracking:
 * - Predict ticket resolution satisfaction (0-100)
 * - Identify satisfaction influencers
 * - Track prediction accuracy vs actual satisfaction
 * - NPS and CES prediction
 * - Trend analysis by agent, ticket type, segment
 * - At-risk customer identification
 * - Proactive intervention recommendations
 */
class SatisfactionPredictionService extends PersonalizationFramework
{
    public function __construct(int $companyId = 0)
    {
        parent::__construct($companyId);
    }

    private const SATISFACTION_FACTORS = [
        'first_contact_resolution' => 0.25,
        'resolution_speed' => 0.20,
        'communication_quality' => 0.20,
        'solution_quality' => 0.20,
        'process_smoothness' => 0.15,
    ];

    /**
     * Predict ticket resolution satisfaction (0-100).
     *
     * @return array<string, mixed>
     */
    public function predictTicketSatisfaction(Ticket $ticket): array
    {
        try {
            $factors = $this->calculateSatisfactionFactors($ticket);

            $satisfactionScore = $this->aggregateSatisfactionScore($factors);
            $confidence = $this->calculatePredictionConfidence($factors);
            $influencers = $this->identifyMainInfluencers($factors);

            return [
                'ticket_id' => $ticket->id,
                'predicted_satisfaction' => round($satisfactionScore, 1),
                'satisfaction_level' => $this->satisfactionLevel($satisfactionScore),
                'confidence' => round($confidence, 2),
                'influencers' => $influencers,
                'factor_scores' => $factors,
                'at_risk' => $satisfactionScore < 60,
                'predicted_at' => now(),
            ];
        } catch (\Throwable $e) {
            return [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'predicted_satisfaction' => 50,
            ];
        }
    }

    /**
     * Identify satisfaction influencers.
     *
     * @return array<string, float>
     */
    public function identifySatisfactionInfluencers(Ticket $ticket): array
    {
        $factors = $this->calculateSatisfactionFactors($ticket);

        // Weight the factors by their importance
        $influencers = [];
        foreach (self::SATISFACTION_FACTORS as $factor => $weight) {
            $score = $factors[$factor] ?? 0.5;
            $influencers[$factor] = round($score * $weight, 3);
        }

        arsort($influencers);
        return array_slice($influencers, 0, 3);
    }

    /**
     * Track prediction accuracy vs actual satisfaction.
     *
     * @return array<string, mixed>
     */
    public function trackPredictionAccuracy(Ticket $ticket): array
    {
        $prediction = $this->predictTicketSatisfaction($ticket);
        $actualSatisfaction = $ticket->satisfaction_score ?? null;

        if ($actualSatisfaction === null) {
            return [
                'ticket_id' => $ticket->id,
                'prediction_recorded' => true,
                'actual_satisfaction' => null,
                'accuracy_status' => 'pending_feedback',
            ];
        }

        $difference = abs($prediction['predicted_satisfaction'] - $actualSatisfaction);
        $isAccurate = $difference <= 10; // Within 10 points

        $result = [
            'ticket_id' => $ticket->id,
            'predicted_satisfaction' => $prediction['predicted_satisfaction'],
            'actual_satisfaction' => $actualSatisfaction,
            'difference' => round($difference, 1),
            'is_accurate' => $isAccurate,
            'accuracy_percentage' => round(max(0, 100 - $difference), 2),
        ];

        $this->recordAccuracyMetric($result);

        return $result;
    }

    /**
     * Predict NPS (Net Promoter Score).
     *
     * @return array<string, mixed>
     */
    public function predictNPS(Ticket $ticket): array
    {
        $satisfaction = $this->predictTicketSatisfaction($ticket);
        $satisfactionScore = $satisfaction['predicted_satisfaction'];

        // Convert satisfaction score to likely NPS response (0-10)
        $npsScore = $this->satisfactionToNps($satisfactionScore);
        $npsCategory = $this->categorizesNps($npsScore);

        return [
            'ticket_id' => $ticket->id,
            'predicted_nps_score' => round($npsScore, 1),
            'nps_category' => $npsCategory, // 'promoter', 'passive', 'detractor'
            'satisfaction_basis' => $satisfactionScore,
        ];
    }

    /**
     * Predict Customer Effort Score (CES).
     *
     * @return array<string, mixed>
     */
    public function predictCES(Ticket $ticket): array
    {
        $processSmoothness = $this->calculateProcessSmoothness($ticket);
        $resolutionSpeed = $this->calculateResolutionSpeed($ticket);

        // CES is based on effort required (lower is better)
        $effortScore = (1 - $processSmoothness) * 50 + (1 - $resolutionSpeed) * 50;
        $cesScore = max(1, min(7, 7 - ($effortScore / 100) * 6)); // Scale 1-7

        return [
            'ticket_id' => $ticket->id,
            'predicted_ces_score' => round($cesScore, 1),
            'effort_level' => $this->categorizeEffort($effortScore),
            'smoothness_score' => round($processSmoothness, 2),
            'speed_score' => round($resolutionSpeed, 2),
        ];
    }

    /**
     * Monitor satisfaction trends by agent.
     *
     * @return array<string, mixed>
     */
    public function monitorAgentSatisfactionTrends(int $agentId): array
    {
        $agent = \Modules\Core\Models\User::find($agentId);
        if (!$agent) {
            return [];
        }

        $tickets = Ticket::where('assignee_id', $agentId)
            ->where('status', 'resolved')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $predictions = [];
        $actualScores = [];
        $npsScores = [];
        $cesScores = [];

        foreach ($tickets as $ticket) {
            $prediction = $this->predictTicketSatisfaction($ticket);
            $predictions[] = $prediction['predicted_satisfaction'];

            if ($ticket->satisfaction_score) {
                $actualScores[] = $ticket->satisfaction_score;
            }

            $nps = $this->predictNPS($ticket);
            $npsScores[] = $nps['predicted_nps_score'];

            $ces = $this->predictCES($ticket);
            $cesScores[] = $ces['predicted_ces_score'];
        }

        return [
            'agent_id' => $agentId,
            'tickets_analyzed' => count($tickets),
            'average_predicted_satisfaction' => count($predictions) > 0 ? round(array_sum($predictions) / count($predictions), 1) : 0,
            'average_actual_satisfaction' => count($actualScores) > 0 ? round(array_sum($actualScores) / count($actualScores), 1) : 0,
            'average_nps' => count($npsScores) > 0 ? round(array_sum($npsScores) / count($npsScores), 1) : 0,
            'average_ces' => count($cesScores) > 0 ? round(array_sum($cesScores) / count($cesScores), 1) : 0,
            'trend' => $this->calculateTrend($predictions, 10),
        ];
    }

    /**
     * Monitor satisfaction trends by ticket type.
     *
     * @return array<string, mixed>
     */
    public function monitorCategoryTrends(string $category): array
    {
        $tickets = Ticket::where('category', $category)
            ->where('status', 'resolved')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $predictions = [];
        $actualScores = [];

        foreach ($tickets as $ticket) {
            $prediction = $this->predictTicketSatisfaction($ticket);
            $predictions[] = $prediction['predicted_satisfaction'];

            if ($ticket->satisfaction_score) {
                $actualScores[] = $ticket->satisfaction_score;
            }
        }

        return [
            'category' => $category,
            'tickets_analyzed' => count($tickets),
            'average_predicted_satisfaction' => count($predictions) > 0 ? round(array_sum($predictions) / count($predictions), 1) : 0,
            'average_actual_satisfaction' => count($actualScores) > 0 ? round(array_sum($actualScores) / count($actualScores), 1) : 0,
            'trend' => $this->calculateTrend($predictions, 10),
        ];
    }

    /**
     * Monitor satisfaction trends by customer segment.
     *
     * @return array<string, mixed>
     */
    public function monitorSegmentTrends(string $segment): array
    {
        // In production, segment would be based on customer attributes
        $tickets = Ticket::whereHas('customer', fn ($q) => $q->where('segment', $segment))
            ->where('status', 'resolved')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $predictions = [];
        $actualScores = [];

        foreach ($tickets as $ticket) {
            $prediction = $this->predictTicketSatisfaction($ticket);
            $predictions[] = $prediction['predicted_satisfaction'];

            if ($ticket->satisfaction_score) {
                $actualScores[] = $ticket->satisfaction_score;
            }
        }

        return [
            'segment' => $segment,
            'tickets_analyzed' => count($tickets),
            'average_predicted_satisfaction' => count($predictions) > 0 ? round(array_sum($predictions) / count($predictions), 1) : 0,
            'average_actual_satisfaction' => count($actualScores) > 0 ? round(array_sum($actualScores) / count($actualScores), 1) : 0,
            'trend' => $this->calculateTrend($predictions, 10),
        ];
    }

    /**
     * Identify at-risk customers (likely to be unsatisfied).
     *
     * @return array<int, array<string, mixed>>
     */
    public function identifyAtRiskCustomers(): array
    {
        $tickets = Ticket::where('status', 'open')
            ->orWhere('status', 'in_progress')
            ->get();

        $atRisk = [];

        foreach ($tickets as $ticket) {
            $prediction = $this->predictTicketSatisfaction($ticket);

            if ($prediction['at_risk']) {
                $atRisk[] = [
                    'ticket_id' => $ticket->id,
                    'customer_id' => $ticket->customer_id,
                    'customer_name' => $ticket->customer?->name,
                    'predicted_satisfaction' => $prediction['predicted_satisfaction'],
                    'risk_score' => round(100 - $prediction['predicted_satisfaction'], 0),
                    'main_influencer' => key($prediction['influencers']),
                ];
            }
        }

        usort($atRisk, fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        return $atRisk;
    }

    /**
     * Generate proactive intervention recommendations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateInterventionRecommendations(Ticket $ticket): array
    {
        $prediction = $this->predictTicketSatisfaction($ticket);
        $recommendations = [];

        if ($prediction['at_risk']) {
            $influencers = $prediction['influencers'];
            $mainIssue = key($influencers);

            $recommendations = match ($mainIssue) {
                'first_contact_resolution' => [
                    [
                        'action' => 'escalate_to_specialist',
                        'reason' => 'Issue requires specialist expertise for resolution',
                        'priority' => 'high',
                    ],
                ],
                'resolution_speed' => [
                    [
                        'action' => 'expedite_resolution',
                        'reason' => 'Accelerate resolution to improve satisfaction',
                        'priority' => 'high',
                    ],
                    [
                        'action' => 'prioritize_ticket',
                        'reason' => 'Move ticket up in queue',
                        'priority' => 'high',
                    ],
                ],
                'communication_quality' => [
                    [
                        'action' => 'assign_senior_agent',
                        'reason' => 'Senior agent can provide better communication',
                        'priority' => 'medium',
                    ],
                ],
                'solution_quality' => [
                    [
                        'action' => 'quality_review',
                        'reason' => 'Review proposed solution with QA team',
                        'priority' => 'high',
                    ],
                ],
                'process_smoothness' => [
                    [
                        'action' => 'simplify_process',
                        'reason' => 'Streamline process for customer ease',
                        'priority' => 'medium',
                    ],
                ],
                default => [],
            };
        }

        return $recommendations;
    }

    /**
     * Batch predict satisfaction for multiple tickets.
     *
     * @param array<int> $ticketIds
     * @return array<int, array<string, mixed>>
     */
    public function batchPredictSatisfaction(array $ticketIds): array
    {
        $results = [];

        foreach ($ticketIds as $ticketId) {
            try {
                $ticket = Ticket::find($ticketId);
                if ($ticket) {
                    $results[$ticketId] = $this->predictTicketSatisfaction($ticket);
                }
            } catch (\Throwable $e) {
                $results[$ticketId] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Get satisfaction metrics dashboard data.
     *
     * @return array<string, mixed>
     */
    public function getSatisfactionMetrics(): array
    {
        $tickets = Ticket::where('status', 'resolved')
            ->orWhere('status', 'closed')
            ->limit(100)
            ->get();

        $predictions = [];
        $actual = [];

        foreach ($tickets as $ticket) {
            $prediction = $this->predictTicketSatisfaction($ticket);
            $predictions[] = $prediction['predicted_satisfaction'];

            if ($ticket->satisfaction_score) {
                $actual[] = $ticket->satisfaction_score;
            }
        }

        return [
            'total_tickets' => count($tickets),
            'average_predicted_satisfaction' => count($predictions) > 0 ? round(array_sum($predictions) / count($predictions), 1) : 0,
            'average_actual_satisfaction' => count($actual) > 0 ? round(array_sum($actual) / count($actual), 1) : 0,
            'min_satisfaction' => count($predictions) > 0 ? min($predictions) : 0,
            'max_satisfaction' => count($predictions) > 0 ? max($predictions) : 100,
            'at_risk_count' => count(array_filter($predictions, fn ($p) => $p < 60)),
        ];
    }

    // ==================== Private Helper Methods ====================

    /**
     * Calculate all satisfaction factors for a ticket.
     *
     * @return array<string, float>
     */
    private function calculateSatisfactionFactors(Ticket $ticket): array
    {
        return [
            'first_contact_resolution' => $this->calculateFirstContactResolution($ticket),
            'resolution_speed' => $this->calculateResolutionSpeed($ticket),
            'communication_quality' => $this->calculateCommunicationQuality($ticket),
            'solution_quality' => $this->calculateSolutionQuality($ticket),
            'process_smoothness' => $this->calculateProcessSmoothness($ticket),
        ];
    }

    /**
     * Calculate first contact resolution likelihood.
     */
    private function calculateFirstContactResolution(Ticket $ticket): float
    {
        $messageCount = $ticket->messages()->count();

        if ($messageCount <= 2) {
            return 0.95; // High likelihood
        } elseif ($messageCount <= 5) {
            return 0.75;
        } elseif ($messageCount <= 10) {
            return 0.5;
        }

        return 0.2; // Low likelihood
    }

    /**
     * Calculate resolution speed score.
     */
    private function calculateResolutionSpeed(Ticket $ticket): float
    {
        if (!$ticket->resolved_at && !$ticket->closed_at) {
            return 0.3; // Still open = poor
        }

        $resolveTime = $ticket->resolved_at ?? $ticket->closed_at;
        $hoursToResolve = $ticket->created_at->diffInHours($resolveTime);

        // Base speed on SLA targets
        $slaHours = 24; // Default
        if ($ticket->sla_due_at) {
            $slaHours = $ticket->created_at->diffInHours($ticket->sla_due_at);
        }

        if ($hoursToResolve <= $slaHours / 2) {
            return 1.0; // Excellent
        } elseif ($hoursToResolve <= $slaHours) {
            return 0.8; // Good
        } elseif ($hoursToResolve <= $slaHours * 1.5) {
            return 0.6; // Acceptable
        }

        return 0.3; // Poor
    }

    /**
     * Calculate communication quality score.
     */
    private function calculateCommunicationQuality(Ticket $ticket): float
    {
        $sentimentService = app(SentimentAnalysisService::class);
        $analysis = $sentimentService->analyzeTicketSentiment($ticket);

        $baseScore = match ($analysis['sentiment']) {
            'positive' => 0.9,
            'neutral' => 0.7,
            'mixed' => 0.6,
            'negative' => 0.3,
            default => 0.5,
        };

        // Adjust based on response quality
        $responseCount = $ticket->responses()->count();
        if ($responseCount > 5) {
            $baseScore += 0.1; // Bonus for thorough communication
        }

        return min($baseScore, 1.0);
    }

    /**
     * Calculate solution quality score.
     */
    private function calculateSolutionQuality(Ticket $ticket): float
    {
        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            // If resolved/closed, assume quality is good unless feedback says otherwise
            if ($ticket->satisfaction_score && $ticket->satisfaction_score < 50) {
                return 0.4;
            }
            return 0.8;
        }

        return 0.5; // Neutral for open tickets
    }

    /**
     * Calculate process smoothness score.
     */
    private function calculateProcessSmoothness(Ticket $ticket): float
    {
        $escalationCount = Ticket::where('id', $ticket->id)
            ->whereNotNull('escalation_level')
            ->count();

        $reassignmentCount = 0; // Would track from history

        $complexity = 0.0;
        if ($escalationCount > 0) {
            $complexity += 0.3;
        }
        if ($reassignmentCount > 0) {
            $complexity += 0.2;
        }

        return max(0.0, 1.0 - $complexity);
    }

    /**
     * Aggregate satisfaction score from factors.
     *
     * @param array<string, float> $factors
     */
    private function aggregateSatisfactionScore(array $factors): float
    {
        $score = 0.0;

        foreach (self::SATISFACTION_FACTORS as $factor => $weight) {
            $factorScore = $factors[$factor] ?? 0.5;
            $score += $factorScore * $weight * 100;
        }

        return min(max($score, 0), 100);
    }

    /**
     * Calculate prediction confidence.
     *
     * @param array<string, float> $factors
     */
    private function calculatePredictionConfidence(array $factors): float
    {
        $values = array_values($factors);
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / count($values);
        $stdDev = sqrt($variance);

        // Higher confidence when scores are consistent
        return max(0.4, 1.0 - ($stdDev * 0.3));
    }

    /**
     * Identify main influencers from factors.
     *
     * @param array<string, float> $factors
     * @return array<string, float>
     */
    private function identifyMainInfluencers(array $factors): array
    {
        $influencers = [];

        foreach (self::SATISFACTION_FACTORS as $factor => $weight) {
            $score = ($factors[$factor] ?? 0.5) * $weight;
            $influencers[$factor] = round($score, 3);
        }

        arsort($influencers);
        return array_slice($influencers, 0, 3);
    }

    /**
     * Determine satisfaction level text.
     */
    private function satisfactionLevel(float $score): string
    {
        if ($score >= 90) {
            return 'extremely_satisfied';
        } elseif ($score >= 75) {
            return 'satisfied';
        } elseif ($score >= 60) {
            return 'neutral';
        } elseif ($score >= 40) {
            return 'dissatisfied';
        }

        return 'very_dissatisfied';
    }

    /**
     * Convert satisfaction score to NPS score.
     */
    private function satisfactionToNps(float $satisfactionScore): float
    {
        // Scale 0-100 satisfaction to 0-10 NPS
        return round(($satisfactionScore / 100) * 10, 1);
    }

    /**
     * Categorize NPS score.
     */
    private function categorizesNps(float $npsScore): string
    {
        if ($npsScore >= 9) {
            return 'promoter';
        } elseif ($npsScore >= 7) {
            return 'passive';
        }

        return 'detractor';
    }

    /**
     * Categorize effort level.
     */
    private function categorizeEffort(float $effortScore): string
    {
        if ($effortScore <= 25) {
            return 'very_easy';
        } elseif ($effortScore <= 50) {
            return 'easy';
        } elseif ($effortScore <= 75) {
            return 'moderate';
        }

        return 'difficult';
    }

    /**
     * Calculate trend from score history.
     *
     * @param array<float> $scores
     */
    private function calculateTrend(array $scores, int $recentCount = 10): string
    {
        if (count($scores) < 2) {
            return 'insufficient_data';
        }

        $recent = array_slice($scores, -min($recentCount, count($scores)));
        $older = array_slice($scores, 0, max(1, count($scores) - $recentCount));

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
     * Record accuracy metric for model training.
     *
     * @param array<string, mixed> $result
     */
    private function recordAccuracyMetric(array $result): void
    {
        AuditLog::create([
            'action' => 'satisfaction_prediction_tracked',
            'model_type' => Ticket::class,
            'model_id' => $result['ticket_id'],
            'changes' => $result,
            'user_id' => null,
        ]);
    }

    // ==================== PersonalizationFramework Abstract Methods ====================

    /**
     * Determine customer satisfaction segment
     */
    protected function determineUserSegment(int $userId): array
    {
        $ticket = Ticket::find($userId);
        if (!$ticket) {
            return [];
        }

        $prediction = $this->predictTicketSatisfaction($ticket);
        $level = $prediction['satisfaction_level'] ?? 'neutral';

        return [
            'satisfaction_level' => $level,
            'predicted_score' => $prediction['predicted_satisfaction'] ?? 50,
            'at_risk' => $prediction['at_risk'] ?? false,
            'confidence' => $prediction['confidence'] ?? 0.5,
        ];
    }

    /**
     * Calculate customer satisfaction intervention score
     */
    protected function calculateScore(int $userId, int $itemId, array $segment): float
    {
        $baseScore = ($segment['predicted_score'] ?? 50) / 100;
        $confidenceBoost = ($segment['confidence'] ?? 0.5) * 0.2;
        $riskPenalty = $segment['at_risk'] ? 0.3 : 0.0;

        return min(1.0, $baseScore + $confidenceBoost - $riskPenalty);
    }

    /**
     * Get candidate interventions for at-risk customers
     */
    protected function getCandidateItems(int $userId, array $segment): Collection
    {
        $ticket = Ticket::find($userId);
        if (!$ticket) {
            return collect();
        }

        // Get intervention recommendations
        $recommendations = $this->generateInterventionRecommendations($ticket);

        return collect($recommendations);
    }

    /**
     * Store customer satisfaction preferences and feedback
     */
    protected function storePreferences(int $userId, array $preferences): void
    {
        $ticket = Ticket::find($userId);
        if (!$ticket) {
            return;
        }

        DB::table('satisfaction_prediction_preferences')->updateOrInsert(
            ['ticket_id' => $ticket->id, 'company_id' => $this->companyId],
            [
                'satisfaction_level' => $preferences['level'] ?? 'neutral',
                'prediction_factors' => json_encode($preferences['factors'] ?? []),
                'intervention_type' => $preferences['intervention'] ?? 'none',
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Record satisfaction prediction interactions and outcomes
     */
    protected function recordInteraction(int $userId, int $itemId, string $action, ?float $value): void
    {
        try {
            $ticket = Ticket::find($userId);
            if (!$ticket) {
                return;
            }

            DB::table('satisfaction_interactions')->insert([
                'ticket_id' => $ticket->id,
                'action_type' => $action,
                'satisfaction_impact' => $value,
                'company_id' => $this->companyId,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to record satisfaction interaction', [
                'ticket_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
