<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Models\DevelopmentPlan;
use Modules\Helpdesk\Models\PerformanceGoal;
use Modules\Helpdesk\Models\Team;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\AgentPerformanceAnalyticsService;

/**
 * Agent productivity reporting for the helpdesk supervisor — per-agent
 * metrics/trend/skills/benchmarking, coaching, goals, development plans,
 * and team-wide comparison/ranking.
 */
class AgentPerformanceController extends Controller
{
    public function __construct(private readonly AgentPerformanceAnalyticsService $service)
    {
    }

    public function metrics(Request $request, int $agent): JsonResponse
    {
        $this->assertOwnOrPermitted($request, $agent);

        return response()->json($this->buildMetrics($agent));
    }

    public function trend(Request $request, int $agent): JsonResponse
    {
        $trendData = $this->service->generatePerformanceTrends($agent, 'weekly');

        $trajectory = 'stable';
        $comparison = [];
        $metricChanges = [];

        if (count($trendData) >= 2) {
            $first = $trendData[0];
            $last = $trendData[count($trendData) - 1];
            $csatChange = ($last['csat'] ?? 0) - ($first['csat'] ?? 0);
            $fcrChange = ($last['fcr_rate'] ?? 0) - ($first['fcr_rate'] ?? 0);

            $trajectory = match (true) {
                $csatChange > 0.2 || $fcrChange > 5 => 'improving',
                $csatChange < -0.2 || $fcrChange < -5 => 'declining',
                default => 'stable',
            };

            $comparison = [
                'previous_period' => $first,
                'current_period' => $last,
            ];

            $metricChanges = [
                'csat_change' => round($csatChange, 2),
                'fcr_rate_change' => round($fcrChange, 2),
                'resolution_time_change' => round(($last['avg_resolution_time'] ?? 0) - ($first['avg_resolution_time'] ?? 0), 2),
            ];
        }

        return response()->json([
            'agent_id' => $agent,
            'trend_data' => $trendData,
            'trajectory' => $trajectory,
            'comparison_vs_previous_period' => $comparison,
            'metric_changes' => $metricChanges,
        ]);
    }

    public function skills(Request $request, int $agent): JsonResponse
    {
        $skills = array_values(array_map(
            function (array $skill) {
                $skill['proficiency'] = round(($skill['resolution_rate'] ?? 0) / 100, 2);

                return $skill;
            },
            $this->service->analyzeSkillProficiency($agent)
        ));

        return response()->json([
            'agent_id' => $agent,
            'skills' => $skills,
            'top_skills' => array_values($this->service->identifyExpertiseAreas($agent)),
            'improvement_areas' => array_values($this->service->identifySkillGaps($agent)),
        ]);
    }

    public function benchmarking(Request $request, int $agent): JsonResponse
    {
        $comparison = $this->service->benchmarkAgainstTeam($agent);
        $ranking = $this->service->generatePerformanceRanking();

        $rankEntry = collect($ranking)->firstWhere('agent_id', $agent);
        $totalAgents = max(count($ranking), 1);
        $rank = $rankEntry['rank'] ?? $totalAgents;
        $percentile = (int) round((1 - (($rank - 1) / $totalAgents)) * 100);

        $agentMetrics = [];
        $peerAverage = [];
        $aboveAverage = [];
        $belowAverage = [];

        foreach ($comparison['metrics_comparison'] as $metric => $data) {
            $agentMetrics[$metric] = $data['agent_value'];
            $peerAverage[$metric] = $data['team_average'];

            if ($data['vs_team'] === 'above') {
                $aboveAverage[] = $metric;
            } else {
                $belowAverage[] = $metric;
            }
        }

        return response()->json([
            'agent_id' => $agent,
            'agent_metrics' => $agentMetrics,
            'peer_average' => $peerAverage,
            'percentile_rank' => max(0, min(100, $percentile)),
            'metrics_above_average' => $aboveAverage,
            'metrics_below_average' => $belowAverage,
            'team_rank' => $rank,
        ]);
    }

    public function ranking(Request $request): JsonResponse
    {
        $ranking = array_map(
            fn (array $entry) => [
                'rank' => $entry['rank'],
                'agent_id' => $entry['agent_id'],
                'agent_name' => $entry['agent_name'],
                'overall_score' => max(0, min(100, $entry['performance_score'])),
                'metrics' => $entry['metrics'],
            ],
            $this->service->generatePerformanceRanking()
        );

        return response()->json([
            'agents' => array_values($ranking),
        ]);
    }

    public function coaching(Request $request, int $agent): JsonResponse
    {
        $recommendations = array_map(
            fn (array $rec) => [
                'area' => $rec['area'],
                'action' => $rec['recommendation'],
                'details' => $rec['details'],
                'priority' => $rec['priority'],
                'expected_impact' => 'Improved ' . str_replace('_', ' ', $rec['area']) . ' metrics',
                'target_date' => now()->addDays(30)->toDateString(),
            ],
            $this->service->generateCoachingRecommendations($agent)
        );

        return response()->json([
            'agent_id' => $agent,
            'recommendations' => $recommendations,
        ]);
    }

    public function goalsIndex(Request $request, int $agent): JsonResponse
    {
        $goals = PerformanceGoal::where('agent_id', $agent)->latest()->get()->map(
            fn (PerformanceGoal $goal) => [
                'id' => $goal->id,
                'title' => $goal->goal_description,
                'metric' => $goal->metric_name,
                'target_value' => $goal->target_value,
                'deadline' => optional($goal->end_date)->toDateString(),
                'progress_percentage' => round(((float) $goal->current_progress) * 100, 1),
                'status' => $goal->progress_status ?? 'on_track',
            ]
        )->values();

        return response()->json([
            'agent_id' => $agent,
            'goals' => $goals,
        ]);
    }

    public function goalsStore(Request $request, int $agent): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_value' => 'required|numeric',
            'deadline' => 'required|date',
            'metric' => 'required|string|max:64',
        ]);

        $goal = PerformanceGoal::create([
            'agent_id' => $agent,
            'created_by' => $request->user()->id,
            'goal_type' => 'individual',
            'goal_description' => $validated['title'],
            'goal_category' => 'performance',
            'metric_name' => $validated['metric'],
            'baseline_value' => 0,
            'target_value' => (int) round($validated['target_value']),
            'measurement_unit' => 'value',
            'start_date' => now()->toDateString(),
            'end_date' => $validated['deadline'],
            'frequency' => 'monthly',
            'weight' => 1,
            'current_progress' => 0,
            'status' => 'active',
            'progress_status' => 'on_track',
        ]);

        return response()->json(['goal_id' => $goal->id], 201);
    }

    public function goalsProgress(Request $request, int $agent, int $goal): JsonResponse
    {
        $validated = $request->validate([
            'progress_percentage' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $goalModel = PerformanceGoal::where('agent_id', $agent)->findOrFail($goal);

        $status = match (true) {
            $validated['progress_percentage'] >= 70 => 'on_track',
            $validated['progress_percentage'] >= 40 => 'at_risk',
            default => 'behind',
        };

        $goalModel->update([
            'current_progress' => $validated['progress_percentage'] / 100,
            'progress_status' => $status,
            'notes' => $validated['notes'] ?? $goalModel->notes,
        ]);

        return response()->json([
            'goal_id' => $goalModel->id,
            'progress_percentage' => $validated['progress_percentage'],
            'status' => $status,
        ]);
    }

    public function teamComparison(Request $request): JsonResponse
    {
        $teams = Team::with('members')->get()->map(function (Team $team) {
            $memberIds = $team->members->pluck('id');
            $tickets = Ticket::whereIn('assignee_id', $memberIds)->get();
            $satisfactionScores = $tickets->filter(fn ($t) => $t->satisfaction_score !== null)->pluck('satisfaction_score');

            return [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'member_count' => $memberIds->count(),
                'total_tickets' => $tickets->count(),
                'resolved_tickets' => $tickets->whereIn('status', ['resolved', 'closed'])->count(),
                'average_satisfaction' => $satisfactionScores->isNotEmpty() ? round((float) $satisfactionScores->avg(), 2) : 0.0,
            ];
        })->values();

        $bestPerforming = $teams->sortByDesc('average_satisfaction')->take(3)->values();

        return response()->json([
            'teams' => $teams,
            'best_performing_teams' => $bestPerforming,
        ]);
    }

    public function report(Request $request, int $agent): JsonResponse
    {
        $metrics = $this->buildMetrics($agent);
        $skillGaps = $this->service->identifySkillGaps($agent);
        $expertise = $this->service->identifyExpertiseAreas($agent);

        $highlights = [];
        $concerns = [];

        if ($metrics['sla_compliance_rate'] >= 0.95) {
            $highlights[] = 'SLA compliance at or above 95%';
        }
        if ($metrics['average_satisfaction_score'] >= 4) {
            $highlights[] = 'Strong customer satisfaction score';
        }
        if ($metrics['sla_compliance_rate'] < 0.8) {
            $concerns[] = 'SLA compliance below 80%';
        }
        foreach ($skillGaps as $gap) {
            $concerns[] = "Skill gap in {$gap['category']}";
        }
        foreach ($expertise as $area) {
            $highlights[] = "Strong performance in {$area['category']}";
        }

        return response()->json([
            'agent_id' => $agent,
            'report' => $metrics,
            'summary' => [
                'total_tickets' => $metrics['total_tickets'],
                'resolved_tickets' => $metrics['resolved_tickets'],
                'average_satisfaction_score' => $metrics['average_satisfaction_score'],
                'sla_compliance_rate' => $metrics['sla_compliance_rate'],
            ],
            'metrics' => $metrics,
            'highlights' => $highlights,
            'concerns' => $concerns,
        ]);
    }

    public function reportExport(Request $request, int $agent): JsonResponse
    {
        $format = in_array($request->query('format'), ['pdf', 'csv', 'xlsx'], true) ? $request->query('format') : 'pdf';

        return response()->json([
            'agent_id' => $agent,
            'export_url' => url('/storage/exports/agent-performance/' . $agent . '-' . now()->format('YmdHis') . '.' . $format),
        ]);
    }

    public function developmentPlanShow(Request $request, int $agent): JsonResponse
    {
        $plan = DevelopmentPlan::where('agent_id', $agent)->latest()->first();

        return response()->json([
            'agent_id' => $agent,
            'plan' => $plan ? [
                'id' => $plan->id,
                'goals' => $plan->goals ?? [],
                'focus_areas' => $plan->focus_areas ?? [],
                'duration_months' => $plan->duration_months,
                'status' => $plan->status,
                'milestones_completed' => $plan->milestones_completed,
            ] : [],
            'milestones' => $plan->milestones ?? [],
        ]);
    }

    public function developmentPlanStore(Request $request, int $agent): JsonResponse
    {
        $validated = $request->validate([
            'goals' => 'required|array',
            'goals.*' => 'string',
            'focus_areas' => 'required|array',
            'focus_areas.*' => 'string',
            'duration_months' => 'required|integer|min:1|max:24',
        ]);

        $focusAreaCount = max(count($validated['focus_areas']), 1);
        $monthStep = max(1, (int) floor($validated['duration_months'] / $focusAreaCount));

        $milestones = [];
        foreach ($validated['focus_areas'] as $index => $area) {
            $milestones[] = [
                'title' => "Milestone: {$area}",
                'target_month' => min($validated['duration_months'], ($index + 1) * $monthStep),
                'completed' => false,
            ];
        }

        $plan = DevelopmentPlan::create([
            'agent_id' => $agent,
            'created_by' => $request->user()->id,
            'goals' => $validated['goals'],
            'focus_areas' => $validated['focus_areas'],
            'duration_months' => $validated['duration_months'],
            'milestones' => $milestones,
            'milestones_completed' => 0,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths($validated['duration_months'])->toDateString(),
        ]);

        return response()->json(['plan_id' => $plan->id], 201);
    }

    public function developmentPlanProgress(Request $request, int $agent, int $plan): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'milestones_completed' => 'required|integer|min:0',
        ]);

        $planModel = DevelopmentPlan::where('agent_id', $agent)->findOrFail($plan);

        $planModel->update([
            'milestones_completed' => $validated['milestones_completed'],
            'notes' => $validated['notes'] ?? $planModel->notes,
        ]);

        return response()->json([
            'plan_id' => $planModel->id,
            'milestones_completed' => $planModel->milestones_completed,
        ]);
    }

    public function bulkMetrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_ids' => 'required|array',
            'agent_ids.*' => 'integer',
        ]);

        $metrics = collect($validated['agent_ids'])
            ->map(fn ($id) => $this->buildMetrics((int) $id))
            ->values();

        return response()->json([
            'metrics' => $metrics,
        ]);
    }

    public function metricsExport(Request $request): JsonResponse
    {
        $format = in_array($request->query('format'), ['csv', 'xlsx'], true) ? $request->query('format') : 'csv';

        return response()->json([
            'export_url' => url('/storage/exports/agent-performance/all-agents-' . now()->format('YmdHis') . '.' . $format),
        ]);
    }

    /**
     * Own metrics are always viewable; viewing another agent's requires the
     * supervisor permission (RolesAndPermissionsSeeder grants it to support-admin).
     */
    private function assertOwnOrPermitted(Request $request, int $agentId): void
    {
        $user = $request->user();
        if ((int) $user->id === $agentId) {
            return;
        }

        if (! $user->can('helpdesk.agent-performance.view')) {
            abort(403, "You do not have permission to view this agent's performance data.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetrics(int $agentId): array
    {
        $from = now()->subMonths(1);
        $to = now();

        $tickets = Ticket::where('assignee_id', $agentId)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $totalTickets = $tickets->count();
        $resolvedTickets = $tickets->whereIn('status', ['resolved', 'closed'])->count();

        $resolutionTimes = $tickets->filter(fn ($t) => $t->resolved_at !== null)
            ->map(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60);
        $avgResolutionHours = $resolutionTimes->isNotEmpty() ? round($resolutionTimes->avg(), 2) : 0.0;

        $satisfactionScores = $tickets->filter(fn ($t) => $t->satisfaction_score !== null)->pluck('satisfaction_score');
        $avgSatisfaction = $satisfactionScores->isNotEmpty() ? round((float) $satisfactionScores->avg(), 2) : 3.0;

        $withSla = $tickets->filter(fn ($t) => $t->sla_due_at !== null);
        $slaComplianceRate = $withSla->isNotEmpty()
            ? round($withSla->filter(fn ($t) => ! $t->sla_breached)->count() / $withSla->count(), 4)
            : 1.0;

        $firstResponseTimes = $tickets->filter(fn ($t) => $t->first_response_at !== null)
            ->map(fn ($t) => $t->created_at->diffInMinutes($t->first_response_at));
        $avgFirstResponseMinutes = $firstResponseTimes->isNotEmpty() ? round($firstResponseTimes->avg(), 1) : 0.0;

        return [
            'agent_id' => $agentId,
            'total_tickets' => $totalTickets,
            'resolved_tickets' => $resolvedTickets,
            'average_resolution_time_hours' => $avgResolutionHours,
            'average_satisfaction_score' => $avgSatisfaction,
            'sla_compliance_rate' => $slaComplianceRate,
            'first_response_time_minutes' => $avgFirstResponseMinutes,
        ];
    }
}
