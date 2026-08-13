<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\SLAPolicy;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SLA (Service Level Agreement) Service
 * Manages response time, resolution time, and escalation tracking.
 * Calculates SLA compliance and sends breach notifications.
 */
class SLAService
{
    /**
     * Get SLA policy for ticket
     */
    public function getPolicyForTicket(Ticket $ticket): ?SLAPolicy
    {
        return SLAPolicy::where('tenant_id', $ticket->tenant_id)
            ->where('priority', $ticket->priority)
            ->where('category', $ticket->category)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Calculate response time target
     * Returns target datetime for first response
     */
    public function getResponseTarget(Ticket $ticket): ?Carbon
    {
        $policy = $this->getPolicyForTicket($ticket);
        
        if (!$policy) {
            return null;
        }

        return $ticket->created_at->addHours($policy->response_time_hours);
    }

    /**
     * Calculate resolution time target
     * Returns target datetime for ticket closure
     */
    public function getResolutionTarget(Ticket $ticket): ?Carbon
    {
        $policy = $this->getPolicyForTicket($ticket);
        
        if (!$policy) {
            return null;
        }

        return $ticket->created_at->addHours($policy->resolution_time_hours);
    }

    /**
     * Check if response SLA is breached
     */
    public function isResponseBreached(Ticket $ticket): bool
    {
        $target = $this->getResponseTarget($ticket);
        
        if (!$target) {
            return false;
        }

        // If first response is recorded, check against it
        if ($ticket->first_response_at) {
            return $ticket->first_response_at->isAfter($target);
        }

        // Check if SLA window has passed
        return now()->isAfter($target);
    }

    /**
     * Check if resolution SLA is breached
     */
    public function isResolutionBreached(Ticket $ticket): bool
    {
        $target = $this->getResolutionTarget($ticket);
        
        if (!$target || $ticket->resolved_at === null) {
            return false;
        }

        return $ticket->resolved_at->isAfter($target);
    }

    /**
     * Get SLA status
     * Returns: 'met', 'at_risk', 'breached'
     */
    public function getStatus(Ticket $ticket): string
    {
        if ($ticket->resolved_at) {
            return $this->isResolutionBreached($ticket) ? 'breached' : 'met';
        }

        $target = $this->getResponseTarget($ticket);
        if (!$target) {
            return 'met';
        }

        // Warning threshold: 80% of SLA window
        $warningTime = $target->diffInSeconds($ticket->created_at) * 0.8;
        $elapsed = now()->diffInSeconds($ticket->created_at);

        if ($elapsed > $warningTime) {
            return 'at_risk';
        }

        return 'met';
    }

    /**
     * Get time remaining in SLA window
     * Returns hours remaining (negative if breached)
     */
    public function getTimeRemaining(Ticket $ticket): float
    {
        $target = $ticket->resolved_at
            ? $this->getResolutionTarget($ticket)
            : $this->getResponseTarget($ticket);

        if (!$target) {
            return 0;
        }

        return now()->diffInHours($target, false);
    }

    /**
     * Get SLA metrics for team
     */
    public function getTeamMetrics(int $teamId, Carbon $start, Carbon $end): array
    {
        $tickets = Ticket::where('team_id', $teamId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalTickets = $tickets->count();
        $resolvedTickets = $tickets->where('resolved_at', '!=', null)->count();
        $breachedTickets = 0;

        foreach ($tickets as $ticket) {
            if ($this->isResolutionBreached($ticket)) {
                $breachedTickets++;
            }
        }

        $compliancePct = $totalTickets > 0 
            ? (($totalTickets - $breachedTickets) / $totalTickets) * 100
            : 100;

        $avgResponseTime = $tickets
            ->whereNotNull('first_response_at')
            ->avg(function ($t) {
                return $t->created_at->diffInMinutes($t->first_response_at);
            });

        $avgResolutionTime = $tickets
            ->whereNotNull('resolved_at')
            ->avg(function ($t) {
                return $t->created_at->diffInHours($t->resolved_at);
            });

        return [
            'total_tickets' => $totalTickets,
            'resolved_tickets' => $resolvedTickets,
            'breached_tickets' => $breachedTickets,
            'compliance_pct' => round($compliancePct, 2),
            'avg_response_time_minutes' => round($avgResponseTime ?? 0, 2),
            'avg_resolution_time_hours' => round($avgResolutionTime ?? 0, 2),
        ];
    }

    /**
     * Get SLA metrics by priority
     */
    public function getMetricsByPriority(int $tenantId, Carbon $start, Carbon $end): array
    {
        $priorities = ['critical', 'high', 'medium', 'low'];
        $metrics = [];

        foreach ($priorities as $priority) {
            $tickets = Ticket::where('tenant_id', $tenantId)
                ->where('priority', $priority)
                ->whereBetween('created_at', [$start, $end])
                ->get();

            $total = $tickets->count();
            $breached = $tickets->filter(fn($t) => $this->isResolutionBreached($t))->count();

            $metrics[$priority] = [
                'total' => $total,
                'breached' => $breached,
                'compliance_pct' => $total > 0 ? (($total - $breached) / $total) * 100 : 100,
            ];
        }

        return $metrics;
    }

    /**
     * Find tickets at risk of SLA breach
     */
    public function getAtRiskTickets(int $tenantId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::where('tenant_id', $tenantId)
            ->where('resolved_at', null)
            ->where('status', '!=', 'closed')
            ->get()
            ->filter(fn($t) => $this->getStatus($t) === 'at_risk')
            ->take($limit)
            ->values();
    }

    /**
     * Get breached tickets
     */
    public function getBreachedTickets(int $tenantId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::where('tenant_id', $tenantId)
            ->get()
            ->filter(fn($t) => $this->getStatus($t) === 'breached')
            ->take($limit)
            ->values();
    }

    /**
     * Create SLA policy
     */
    public function createPolicy(
        int $tenantId,
        string $name,
        string $priority,
        string $category,
        int $responseTimeHours,
        int $resolutionTimeHours
    ): SLAPolicy {
        return SLAPolicy::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'priority' => $priority,
            'category' => $category,
            'response_time_hours' => $responseTimeHours,
            'resolution_time_hours' => $resolutionTimeHours,
            'is_active' => true,
        ]);
    }
}
