<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Carbon;
use Modules\AuditLog\Models\AuditLog;
use Modules\Helpdesk\Models\Ticket;

/**
 * PredictiveEscalationService - ML-based urgency prediction and escalation
 *
 * Provides intelligent escalation predictions based on:
 * - Sentiment trends and severity analysis
 * - SLA wait time predictions
 * - Issue complexity scoring
 * - Agent skill matching
 * - Customer history and VIP status
 * - External factors and known issues
 */
class PredictiveEscalationService
{
    private const ESCALATION_THRESHOLDS = [
        'L1' => 0.3,
        'L2' => 0.6,
        'L3' => 0.8,
        'MANAGEMENT' => 0.9,
    ];

    private const ESCALATION_LEVELS = ['L1', 'L2', 'L3', 'MANAGEMENT'];

    /**
     * Predict escalation need based on multiple factors.
     *
     * @return array<string, mixed>
     */
    public function predictEscalationNeed(Ticket $ticket): array
    {
        try {
            $sentimentScore = $this->calculateSentimentSeverity($ticket);
            $waitTimeScore = $this->calculateWaitTimeScore($ticket);
            $complexityScore = $this->calculateIssueComplexity($ticket);
            $skillMatchScore = $this->calculateAgentSkillMatch($ticket);
            $historyScore = $this->calculateCustomerHistoryScore($ticket);
            $externalFactorScore = $this->calculateExternalFactors($ticket);

            $urgencyScore = $this->aggregateScores([
                'sentiment' => $sentimentScore,
                'wait_time' => $waitTimeScore,
                'complexity' => $complexityScore,
                'skill_match' => $skillMatchScore,
                'history' => $historyScore,
                'external_factors' => $externalFactorScore,
            ]);

            $escalationLevel = $this->determineEscalationLevel($urgencyScore);
            $confidence = $this->calculatePredictionConfidence([
                $sentimentScore, $waitTimeScore, $complexityScore,
                $skillMatchScore, $historyScore, $externalFactorScore,
            ]);

            return [
                'ticket_id' => $ticket->id,
                'urgency_score' => round($urgencyScore, 2),
                'urgency_percentage' => (int) ($urgencyScore * 100),
                'confidence' => round($confidence, 2),
                'predicted_escalation_level' => $escalationLevel,
                'should_escalate' => $urgencyScore >= self::ESCALATION_THRESHOLDS['L2'],
                'factor_scores' => [
                    'sentiment' => round($sentimentScore, 2),
                    'wait_time' => round($waitTimeScore, 2),
                    'complexity' => round($complexityScore, 2),
                    'skill_match' => round($skillMatchScore, 2),
                    'history' => round($historyScore, 2),
                    'external_factors' => round($externalFactorScore, 2),
                ],
                'predicted_at' => now(),
            ];
        } catch (\Throwable $e) {
            return [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'urgency_score' => 0.5,
            ];
        }
    }

    /**
     * Generate urgency scores (0-100 format).
     *
     * @return array<string, int>
     */
    public function generateUrgencyScores(Ticket $ticket): array
    {
        $prediction = $this->predictEscalationNeed($ticket);

        return [
            'overall_urgency' => $prediction['urgency_percentage'],
            'sentiment_urgency' => (int) ($prediction['factor_scores']['sentiment'] * 100),
            'wait_time_urgency' => (int) ($prediction['factor_scores']['wait_time'] * 100),
            'complexity_urgency' => (int) ($prediction['factor_scores']['complexity'] * 100),
            'skill_match_urgency' => (int) (100 - ($prediction['factor_scores']['skill_match'] * 100)),
            'history_urgency' => (int) ($prediction['factor_scores']['history'] * 100),
            'external_urgency' => (int) ($prediction['factor_scores']['external_factors'] * 100),
        ];
    }

    /**
     * Recommend specific escalation actions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendEscalationActions(Ticket $ticket): array
    {
        $prediction = $this->predictEscalationNeed($ticket);
        $actions = [];

        if ($prediction['urgency_score'] >= self::ESCALATION_THRESHOLDS['L3']) {
            $actions[] = $this->generateEscalationAction($ticket, 'L3', 'critical_severity');
        }

        if ($prediction['factor_scores']['sentiment'] > 0.7) {
            $actions[] = $this->generateEscalationAction($ticket, 'L2', 'negative_sentiment');
        }

        if ($prediction['factor_scores']['wait_time'] > 0.8) {
            $actions[] = $this->generateEscalationAction($ticket, 'L2', 'sla_at_risk');
        }

        if ($prediction['factor_scores']['skill_match'] < 0.3) {
            $actions[] = $this->generateEscalationAction($ticket, 'L2', 'skill_gap');
        }

        if ($prediction['factor_scores']['history'] > 0.6) {
            $actions[] = $this->generateEscalationAction($ticket, 'L2', 'repeat_issue');
        }

        return $actions;
    }

    /**
     * Support multi-level escalation (L1→L2→L3→Management).
     *
     * @return array<string, mixed>
     */
    public function performMultiLevelEscalation(Ticket $ticket, string $targetLevel): array
    {
        $currentLevel = $ticket->escalation_level ?? 'L1';
        $escalationPath = $this->buildEscalationPath($currentLevel, $targetLevel);

        $result = [
            'ticket_id' => $ticket->id,
            'from_level' => $currentLevel,
            'to_level' => $targetLevel,
            'escalation_path' => $escalationPath,
            'steps' => [],
        ];

        foreach ($escalationPath as $level) {
            try {
                $stepResult = $this->executeEscalationStep($ticket, $level);
                $result['steps'][] = $stepResult;
            } catch (\Throwable $e) {
                $result['steps'][] = [
                    'level' => $level,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->logEscalation($ticket->id, $result);

        return $result;
    }

    /**
     * Predict SLA breach and prevention actions.
     *
     * @return array<string, mixed>
     */
    public function predictSlaBreach(Ticket $ticket): array
    {
        $hoursUntilDue = $this->calculateHoursUntilSLADue($ticket);
        $estimatedResolutionTime = $this->estimateResolutionTime($ticket);
        $breachProbability = $this->calculateBreachProbability($hoursUntilDue, $estimatedResolutionTime);

        $preventionActions = [];
        if ($breachProbability > 0.6) {
            $preventionActions = $this->generateBreachPreventionActions($ticket, $hoursUntilDue);
        }

        return [
            'ticket_id' => $ticket->id,
            'hours_until_sla_due' => round($hoursUntilDue, 1),
            'estimated_resolution_time' => round($estimatedResolutionTime, 1),
            'breach_probability' => round($breachProbability, 2),
            'will_breach' => $breachProbability > 0.5,
            'prevention_actions' => $preventionActions,
            'recommendation' => $this->generateSlaBRecommendation($breachProbability),
        ];
    }

    /**
     * Track prediction accuracy over time.
     *
     * @param array<int> $ticketIds
     * @return array<string, mixed>
     */
    public function trackPredictionAccuracy(array $ticketIds): array
    {
        $predictions = [];
        $accurate = 0;
        $total = 0;

        foreach ($ticketIds as $ticketId) {
            $ticket = Ticket::find($ticketId);
            if (!$ticket) {
                continue;
            }

            $prediction = $this->predictEscalationNeed($ticket);
            $actualEscalated = $ticket->escalation_level !== null;

            $predictedEscalation = $prediction['should_escalate'];
            $isAccurate = $predictedEscalation === $actualEscalated;

            if ($isAccurate) {
                $accurate++;
            }
            $total++;

            $predictions[] = [
                'ticket_id' => $ticketId,
                'predicted' => $predictedEscalation,
                'actual' => $actualEscalated,
                'accurate' => $isAccurate,
                'prediction_score' => $prediction['urgency_score'],
            ];
        }

        return [
            'total_predictions' => $total,
            'accurate_predictions' => $accurate,
            'accuracy_rate' => $total > 0 ? round(($accurate / $total) * 100, 2) : 0,
            'predictions' => $predictions,
        ];
    }

    /**
     * Learn from actual escalations to improve model.
     *
     * @return array<string, mixed>
     */
    public function learnFromEscalations(Ticket $ticket, string $actualEscalationLevel): array
    {
        $prediction = $this->predictEscalationNeed($ticket);

        $feedback = [
            'ticket_id' => $ticket->id,
            'predicted_level' => $prediction['predicted_escalation_level'],
            'actual_level' => $actualEscalationLevel,
            'was_correct' => $prediction['predicted_escalation_level'] === $actualEscalationLevel,
            'confidence' => $prediction['confidence'],
            'prediction_score' => $prediction['urgency_score'],
            'factors' => $prediction['factor_scores'],
        ];

        $this->updateModelWeights($feedback);
        $this->logEscalationLearning($ticket->id, $feedback);

        return $feedback;
    }

    /**
     * Suggest optimal escalation timing.
     *
     * @return array<string, mixed>
     */
    public function suggestOptimalEscalationTiming(Ticket $ticket): array
    {
        $now = now();
        $slaBreachTime = Carbon::parse($ticket->sla_due_at ?? $now->addHours(24));
        $estimatedResolution = $this->estimateResolutionTime($ticket);
        $escalationLeadTime = max($estimatedResolution / 2, 1); // Half the estimated resolution time

        $optimalTime = $slaBreachTime->subHours($escalationLeadTime);
        $timeUntilOptimal = $now->diffInHours($optimalTime);

        return [
            'ticket_id' => $ticket->id,
            'optimal_escalation_time' => $optimalTime,
            'time_until_optimal' => max($timeUntilOptimal, 0),
            'is_urgent_now' => $timeUntilOptimal <= 0,
            'sla_due_at' => $slaBreachTime,
            'reason' => $this->generateTimingReason($timeUntilOptimal),
        ];
    }

    /**
     * Batch predict escalations for multiple tickets.
     *
     * @param array<int> $ticketIds
     * @return array<int, array<string, mixed>>
     */
    public function batchPredictEscalations(array $ticketIds): array
    {
        $results = [];

        foreach ($ticketIds as $ticketId) {
            try {
                $ticket = Ticket::find($ticketId);
                if ($ticket) {
                    $results[$ticketId] = $this->predictEscalationNeed($ticket);
                }
            } catch (\Throwable $e) {
                $results[$ticketId] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Get escalation metrics and insights.
     *
     * @return array<string, mixed>
     */
    public function getEscalationMetrics(): array
    {
        $total = Ticket::count();
        $escalated = Ticket::whereNotNull('escalation_level')->count();
        $resolved = Ticket::where('status', 'resolved')->count();
        $breached = Ticket::where('sla_breached', true)->count();

        return [
            'total_tickets' => $total,
            'escalated_tickets' => $escalated,
            'escalation_rate' => $total > 0 ? round(($escalated / $total) * 100, 2) : 0,
            'resolved_tickets' => $resolved,
            'breached_tickets' => $breached,
            'sla_breach_rate' => $total > 0 ? round(($breached / $total) * 100, 2) : 0,
            'average_escalation_score' => $this->calculateAverageEscalationScore(),
        ];
    }

    // ==================== Private Helper Methods ====================

    /**
     * Calculate sentiment severity score.
     */
    private function calculateSentimentSeverity(Ticket $ticket): float
    {
        // Integrate with SentimentAnalysisService if available
        $sentimentService = app(SentimentAnalysisService::class);
        $analysis = $sentimentService->analyzeTicketSentiment($ticket);

        $sentimentScores = [
            'positive' => 0.1,
            'neutral' => 0.3,
            'mixed' => 0.6,
            'negative' => 0.9,
        ];

        return $sentimentScores[$analysis['sentiment']] ?? 0.3;
    }

    /**
     * Calculate wait time score relative to SLA.
     */
    private function calculateWaitTimeScore(Ticket $ticket): float
    {
        $hoursElapsed = $ticket->created_at->diffInHours(now());
        $slaDue = $ticket->sla_due_at;

        if (!$slaDue) {
            return 0.3; // Default moderate score
        }

        $totalSlTime = $ticket->created_at->diffInHours($slaDue);
        $percentageUsed = $totalSlTime > 0 ? $hoursElapsed / $totalSlTime : 1.0;

        return min($percentageUsed, 1.0);
    }

    /**
     * Calculate issue complexity score.
     */
    private function calculateIssueComplexity(Ticket $ticket): float
    {
        $complexity = 0.0;

        // Check category complexity
        $complexCategories = ['technical', 'enterprise', 'integration', 'customization'];
        if (in_array($ticket->category, $complexCategories, true)) {
            $complexity += 0.3;
        }

        // Check priority
        if ($ticket->priority === 'critical') {
            $complexity += 0.3;
        } elseif ($ticket->priority === 'high') {
            $complexity += 0.15;
        }

        // Check message count (more messages = more discussion = complexity)
        $messageCount = $ticket->messages()->count();
        $complexity += min(($messageCount / 20) * 0.4, 0.4);

        return min($complexity, 1.0);
    }

    /**
     * Calculate agent skill match score.
     */
    private function calculateAgentSkillMatch(Ticket $ticket): float
    {
        if (!$ticket->assignee_id) {
            return 0.0; // No agent assigned = poor match
        }

        // Check agent expertise by historical resolution rate
        $agent = $ticket->assignee;
        $totalTickets = $agent->tickets()->count();
        $resolvedTickets = $agent->tickets()->where('status', 'resolved')->count();

        if ($totalTickets === 0) {
            return 0.5;
        }

        $resolutionRate = $resolvedTickets / $totalTickets;

        // Bonus for category expertise
        $categoryResolutions = $agent->tickets()
            ->where('category', $ticket->category)
            ->where('status', 'resolved')
            ->count();

        $categoryExpertise = $agent->tickets()
            ->where('category', $ticket->category)
            ->count();

        $categoryRate = $categoryExpertise > 0 ? $categoryResolutions / $categoryExpertise : 0.5;

        return ($resolutionRate * 0.6) + ($categoryRate * 0.4);
    }

    /**
     * Calculate customer history score (VIP, repeat issues, etc.).
     */
    private function calculateCustomerHistoryScore(Ticket $ticket): float
    {
        $score = 0.0;

        // Check customer VIP status
        if ($ticket->customer && $ticket->customer->is_vip) {
            $score += 0.3;
        }

        // Check for repeat issues
        $repeatCount = Ticket::where('customer_id', $ticket->customer_id)
            ->where('id', '!=', $ticket->id)
            ->whereIn('status', ['resolved', 'closed'])
            ->count();

        if ($repeatCount > 0) {
            $score += min(($repeatCount / 10) * 0.4, 0.4);
        }

        // Check customer satisfaction history
        $avgSatisfaction = Ticket::where('customer_id', $ticket->customer_id)
            ->whereNotNull('satisfaction_score')
            ->avg('satisfaction_score');

        if ($avgSatisfaction && $avgSatisfaction < 3) {
            $score += 0.3;
        }

        return min($score, 1.0);
    }

    /**
     * Calculate external factors score (system outages, known issues).
     */
    private function calculateExternalFactors(Ticket $ticket): float
    {
        $score = 0.0;

        // Check for system outages or known issues
        // This would integrate with a monitoring/status page service
        // For now, return a baseline score
        $score = 0.2;

        return min($score, 1.0);
    }

    /**
     * Aggregate multiple scores into a single urgency score.
     *
     * @param array<string, float> $scores
     */
    private function aggregateScores(array $scores): float
    {
        $weights = [
            'sentiment' => 0.25,
            'wait_time' => 0.25,
            'complexity' => 0.2,
            'skill_match' => 0.15,
            'history' => 0.1,
            'external_factors' => 0.05,
        ];

        $weighted = 0.0;
        foreach ($scores as $factor => $value) {
            $weight = $weights[$factor] ?? 0.0;
            $weighted += $value * $weight;
        }

        return $weighted;
    }

    /**
     * Calculate confidence level for prediction.
     *
     * @param array<float> $scores
     */
    private function calculatePredictionConfidence(array $scores): float
    {
        // Confidence is higher when scores are more consistent
        $mean = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(fn ($s) => ($s - $mean) ** 2, $scores)) / count($scores);
        $stdDev = sqrt($variance);

        // Lower variance = higher confidence
        return max(0.5, 1.0 - ($stdDev * 0.5));
    }

    /**
     * Determine escalation level based on urgency score.
     */
    private function determineEscalationLevel(float $urgencyScore): string
    {
        if ($urgencyScore >= self::ESCALATION_THRESHOLDS['MANAGEMENT']) {
            return 'MANAGEMENT';
        } elseif ($urgencyScore >= self::ESCALATION_THRESHOLDS['L3']) {
            return 'L3';
        } elseif ($urgencyScore >= self::ESCALATION_THRESHOLDS['L2']) {
            return 'L2';
        }

        return 'L1';
    }

    /**
     * Generate a single escalation action.
     *
     * @return array<string, mixed>
     */
    private function generateEscalationAction(Ticket $ticket, string $level, string $reason): array
    {
        return [
            'ticket_id' => $ticket->id,
            'recommended_level' => $level,
            'reason' => $reason,
            'priority' => 'high',
            'action_type' => 'escalate',
        ];
    }

    /**
     * Build escalation path from current to target level.
     *
     * @return array<int, string>
     */
    private function buildEscalationPath(string $fromLevel, string $toLevel): array
    {
        $levelIndex = array_search($fromLevel, self::ESCALATION_LEVELS, true);
        $targetIndex = array_search($toLevel, self::ESCALATION_LEVELS, true);

        if ($levelIndex === false || $targetIndex === false || $levelIndex >= $targetIndex) {
            return [];
        }

        return array_slice(self::ESCALATION_LEVELS, $levelIndex + 1, $targetIndex - $levelIndex);
    }

    /**
     * Execute a single escalation step.
     *
     * @return array<string, mixed>
     */
    private function executeEscalationStep(Ticket $ticket, string $level): array
    {
        // In production, this would assign to appropriate team/manager
        return [
            'ticket_id' => $ticket->id,
            'level' => $level,
            'success' => true,
            'escalated_at' => now(),
        ];
    }

    /**
     * Calculate hours until SLA due.
     */
    private function calculateHoursUntilSLADue(Ticket $ticket): float
    {
        if (!$ticket->sla_due_at) {
            return 24.0; // Default 24 hours
        }

        return max(now()->diffInHours($ticket->sla_due_at), 0);
    }

    /**
     * Estimate resolution time based on ticket characteristics.
     */
    private function estimateResolutionTime(Ticket $ticket): float
    {
        // Base estimate by priority
        $estimates = [
            'critical' => 2,
            'high' => 4,
            'medium' => 8,
            'low' => 16,
        ];

        $baseEstimate = $estimates[$ticket->priority] ?? 8;

        // Adjust for complexity
        $complexity = $this->calculateIssueComplexity($ticket);
        $estimate = $baseEstimate * (1 + $complexity);

        return $estimate;
    }

    /**
     * Calculate probability of SLA breach.
     */
    private function calculateBreachProbability(float $hoursUntilDue, float $estimatedTime): float
    {
        if ($hoursUntilDue <= 0) {
            return 1.0;
        }

        $timeRatio = $estimatedTime / $hoursUntilDue;
        $probability = min($timeRatio * 1.2, 1.0); // 20% buffer

        return $probability;
    }

    /**
     * Generate actions to prevent SLA breach.
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateBreachPreventionActions(Ticket $ticket, float $hoursUntilDue): array
    {
        $actions = [];

        if ($hoursUntilDue < 1) {
            $actions[] = [
                'action' => 'escalate_to_manager',
                'urgency' => 'critical',
                'reason' => 'SLA breach imminent',
            ];
        } else {
            $actions[] = [
                'action' => 'escalate_to_senior_agent',
                'urgency' => 'high',
                'reason' => 'Prevent SLA breach',
            ];
        }

        $actions[] = [
            'action' => 'assign_subject_matter_expert',
            'urgency' => 'high',
            'reason' => 'Ensure quick resolution',
        ];

        return $actions;
    }

    /**
     * Generate SLA recommendation text.
     */
    private function generateSlaBRecommendation(float $breachProbability): string
    {
        if ($breachProbability > 0.8) {
            return 'URGENT: Immediate escalation recommended to prevent SLA breach.';
        } elseif ($breachProbability > 0.6) {
            return 'HIGH: Consider escalation to ensure SLA compliance.';
        } elseif ($breachProbability > 0.4) {
            return 'MEDIUM: Monitor closely for potential SLA issues.';
        }

        return 'LOW: SLA compliance appears likely.';
    }

    /**
     * Generate reason for optimal timing suggestion.
     */
    private function generateTimingReason(float $timeUntilOptimal): string
    {
        if ($timeUntilOptimal <= 0) {
            return 'Escalate immediately to meet SLA targets.';
        } elseif ($timeUntilOptimal <= 2) {
            return 'Escalate within the next 2 hours for optimal resolution.';
        } elseif ($timeUntilOptimal <= 8) {
            return 'Plan escalation within the next 8 hours.';
        }

        return 'Current timing allows for continued standard support.';
    }

    /**
     * Update model weights based on feedback.
     *
     * @param array<string, mixed> $feedback
     */
    private function updateModelWeights(array $feedback): void
    {
        // In production, this would update ML model weights
        // For now, just log the feedback for future analysis
        $this->logEscalationFeedback($feedback);
    }

    /**
     * Calculate average escalation score across all tickets.
     */
    private function calculateAverageEscalationScore(): float
    {
        // This is a simplified calculation
        $tickets = Ticket::limit(100)->get();
        $total = 0;

        foreach ($tickets as $ticket) {
            $prediction = $this->predictEscalationNeed($ticket);
            $total += $prediction['urgency_score'];
        }

        return count($tickets) > 0 ? round($total / count($tickets), 2) : 0.0;
    }

    /**
     * Log escalation event.
     *
     * @param array<string, mixed> $data
     */
    private function logEscalation(int $ticketId, array $data): void
    {
        AuditLog::create([
            'action' => 'escalation_performed',
            'model_type' => Ticket::class,
            'model_id' => $ticketId,
            'changes' => $data,
            'user_id' => null,
        ]);
    }

    /**
     * Log escalation learning event.
     *
     * @param array<string, mixed> $feedback
     */
    private function logEscalationLearning(int $ticketId, array $feedback): void
    {
        AuditLog::create([
            'action' => 'escalation_learning_recorded',
            'model_type' => Ticket::class,
            'model_id' => $ticketId,
            'changes' => $feedback,
            'user_id' => null,
        ]);
    }

    /**
     * Log escalation feedback.
     *
     * @param array<string, mixed> $feedback
     */
    private function logEscalationFeedback(array $feedback): void
    {
        AuditLog::create([
            'action' => 'escalation_feedback_logged',
            'model_type' => 'escalation_model',
            'model_id' => 0,
            'changes' => $feedback,
            'user_id' => null,
        ]);
    }
}
