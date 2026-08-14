<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\Team;
use App\Models\User;

/**
 * TicketAssignmentService handles queue-based ticket assignment using round-robin.
 */
class TicketAssignmentService
{
    /**
     * Assign ticket using round-robin strategy.
     */
    public function assignRoundRobin(Ticket $ticket, ?Team $team = null): ?User
    {
        $team = $team ?? $ticket->team;

        if (!$team) {
            Log::warning('Cannot assign ticket: no team specified', [
                'ticket_id' => $ticket->id,
            ]);
            return null;
        }

        // Get active support agents in the team
        $agents = $team->members()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'support-agent');
            })
            ->where('is_active', true)
            ->get();

        if ($agents->isEmpty()) {
            Log::warning('No active support agents available', [
                'ticket_id' => $ticket->id,
                'team_id' => $team->id,
            ]);
            return null;
        }

        // Get least recently assigned agent
        $assignee = $agents->sortBy(function ($agent) {
            return $agent->tickets()
                ->where('status', '!=', 'resolved')
                ->count();
        })->first();

        if ($assignee) {
            $ticket->update(['assignee_id' => $assignee->id]);

            Log::info('Ticket assigned (round-robin)', [
                'ticket_id' => $ticket->id,
                'assignee_id' => $assignee->id,
                'team_id' => $team->id,
            ]);

            // Notify assignment via AlertService
            app(AlertService::class)->notifyTicketAssignment($ticket, $assignee->id);
        }

        return $assignee;
    }

    /**
     * Assign ticket to specific agent.
     */
    public function assignToAgent(Ticket $ticket, User $agent): bool
    {
        // Verify agent is a support agent
        if (!$agent->hasRole('support-agent')) {
            Log::warning('User is not a support agent', [
                'ticket_id' => $ticket->id,
                'user_id' => $agent->id,
            ]);
            return false;
        }

        $ticket->update(['assignee_id' => $agent->id]);

        Log::info('Ticket assigned to agent', [
            'ticket_id' => $ticket->id,
            'assignee_id' => $agent->id,
        ]);

        app(AlertService::class)->notifyTicketAssignment($ticket, $agent->id);

        return true;
    }

    /**
     * Unassign ticket from agent.
     */
    public function unassign(Ticket $ticket): bool
    {
        $previousAssignee = $ticket->assignee_id;

        $ticket->update(['assignee_id' => null]);

        Log::info('Ticket unassigned', [
            'ticket_id' => $ticket->id,
            'previous_assignee_id' => $previousAssignee,
        ]);

        return true;
    }

    /**
     * Get current assignment load for an agent.
     */
    public function getAgentLoad(User $agent): array
    {
        $tickets = $agent->tickets()
            ->where('status', '!=', 'resolved')
            ->get();

        $slaBreached = $tickets->where('sla_breached', true)->count();
        $slaWarnings = $tickets->filter(function ($ticket) {
            return $ticket->sla_due_at && $ticket->sla_due_at->diffInHours(now()) <= 24;
        })->count();

        return [
            'total_tickets' => $tickets->count(),
            'open_tickets' => $tickets->where('status', 'open')->count(),
            'pending_tickets' => $tickets->where('status', 'pending')->count(),
            'sla_breached' => $slaBreached,
            'sla_warnings' => $slaWarnings,
            'avg_resolution_time' => $this->getAvgResolutionTime($agent),
        ];
    }

    /**
     * Get average resolution time for an agent.
     */
    private function getAvgResolutionTime(User $agent): float
    {
        $resolved = $agent->tickets()
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->get();

        if ($resolved->isEmpty()) {
            return 0;
        }

        $totalTime = 0;
        $count = 0;

        foreach ($resolved as $ticket) {
            $time = $ticket->resolved_at->diffInMinutes($ticket->created_at);
            $totalTime += $time;
            $count++;
        }

        return round($totalTime / $count, 2);
    }
}
