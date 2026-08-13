<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Helpdesk\Models\SlaBreach;
use Modules\Helpdesk\Models\SlaPolicy;

class SlaAutomationService
{
    /**
     * Get the appropriate SLA policy for a ticket priority.
     * Returns first active policy matching priority, or null.
     */
    public function getPolicyForPriority(string $priority): ?SlaPolicy
    {
        return SlaPolicy::where('priority', $priority)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if a ticket has breached SLA and record breach if so.
     *
     * @return SlaBreach[]
     */
    public function checkAndRecordBreaches(
        int $ticketId,
        string $priority,
        Carbon $createdAt,
        ?Carbon $firstResponseAt,
        ?Carbon $resolvedAt
    ): array {
        $policy = $this->getPolicyForPriority($priority);

        if ($policy === null) {
            return [];
        }

        $breaches = [];

        // Check response breach
        $responseDeadline = $policy->responseDeadline($createdAt);

        if ($firstResponseAt === null) {
            if (now()->gt($responseDeadline)) {
                $breaches[] = SlaBreach::create([
                    'ticket_id' => $ticketId,
                    'policy_id' => $policy->id,
                    'breach_type' => 'response',
                    'breached_at' => now(),
                    'breach_minutes' => (int) now()->diffInMinutes($responseDeadline),
                ]);
            }
        } elseif ($firstResponseAt->gt($responseDeadline)) {
            $breaches[] = SlaBreach::create([
                'ticket_id' => $ticketId,
                'policy_id' => $policy->id,
                'breach_type' => 'response',
                'breached_at' => $firstResponseAt,
                'breach_minutes' => (int) $firstResponseAt->diffInMinutes($responseDeadline),
            ]);
        }

        // Check resolution breach
        $resolutionDeadline = $policy->resolutionDeadline($createdAt);

        if ($resolvedAt === null) {
            if (now()->gt($resolutionDeadline)) {
                $breaches[] = SlaBreach::create([
                    'ticket_id' => $ticketId,
                    'policy_id' => $policy->id,
                    'breach_type' => 'resolution',
                    'breached_at' => now(),
                    'breach_minutes' => (int) now()->diffInMinutes($resolutionDeadline),
                ]);
            }
        } elseif ($resolvedAt->gt($resolutionDeadline)) {
            $breaches[] = SlaBreach::create([
                'ticket_id' => $ticketId,
                'policy_id' => $policy->id,
                'breach_type' => 'resolution',
                'breached_at' => $resolvedAt,
                'breach_minutes' => (int) $resolvedAt->diffInMinutes($resolutionDeadline),
            ]);
        }

        return $breaches;
    }

    /**
     * Get all unacknowledged breaches.
     */
    public function getPendingBreaches(): Collection
    {
        return SlaBreach::whereNull('acknowledged_at')->get();
    }

    /**
     * Get breaches for a specific ticket.
     */
    public function getTicketBreaches(int $ticketId): Collection
    {
        return SlaBreach::where('ticket_id', $ticketId)->get();
    }

    /**
     * Run escalation check: escalate all unescalated breaches that qualify.
     * Returns count of escalated breaches.
     */
    public function runEscalations(): int
    {
        $count = 0;

        $unescalated = SlaBreach::where('escalated', false)
            ->whereNotNull('policy_id')
            ->with('policy')
            ->get();

        foreach ($unescalated as $breach) {
            /** @var SlaBreach $breach */
            if ($breach->policy === null) {
                continue;
            }

            /** @var SlaPolicy $policy */
            $policy = $breach->policy;

            if (! $policy->escalation_enabled || $policy->escalation_after_minutes === null) {
                continue;
            }

            if ($breach->needsEscalation($policy->escalation_after_minutes)) {
                $breach->escalate();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get SLA compliance stats.
     *
     * @return array{total_tickets_checked: int, compliant: int, breached: int, compliance_rate: float}
     */
    public function getComplianceStats(): array
    {
        $totalBreachedTickets = SlaBreach::distinct('ticket_id')->count('ticket_id');
        $totalCheckedTickets = SlaPolicy::where('is_active', true)->count() > 0
            ? SlaBreach::distinct('ticket_id')->count('ticket_id')
            : 0;

        // We base compliance on distinct ticket IDs that have breaches
        $breachedCount = SlaBreach::distinct('ticket_id')->count('ticket_id');

        // Total tickets assessed = unique ticket IDs in breaches table (those we've checked)
        $totalTickets = $breachedCount; // In this system, we only record when we check

        $compliantCount = 0; // By definition, if in hd_sla_breaches they've breached
        $complianceRate = $totalTickets > 0 ? 0.0 : 100.0;

        return [
            'total_tickets_checked' => $totalTickets,
            'compliant' => $compliantCount,
            'breached' => $breachedCount,
            'compliance_rate' => $complianceRate,
        ];
    }

    /**
     * Get performance report by priority.
     *
     * @return array<int, array{priority: string, avg_response_minutes: float, avg_resolution_minutes: float, breach_count: int}>
     */
    public function getPerformanceReport(): array
    {
        $breachesByPolicy = SlaBreach::query()
            ->join('hd_sla_policies', 'hd_sla_breaches.policy_id', '=', 'hd_sla_policies.id')
            ->selectRaw('
                hd_sla_policies.priority,
                AVG(CASE WHEN hd_sla_breaches.breach_type = ? THEN hd_sla_breaches.breach_minutes ELSE NULL END) as avg_response_minutes,
                AVG(CASE WHEN hd_sla_breaches.breach_type = ? THEN hd_sla_breaches.breach_minutes ELSE NULL END) as avg_resolution_minutes,
                COUNT(*) as breach_count
            ', ['response', 'resolution'])
            ->groupBy('hd_sla_policies.priority')
            ->toBase()
            ->get();

        return $breachesByPolicy->map(fn (object $row) => [
            'priority' => (string) ($row->priority ?? ''),
            'avg_response_minutes' => (float) ($row->avg_response_minutes ?? 0),
            'avg_resolution_minutes' => (float) ($row->avg_resolution_minutes ?? 0),
            'breach_count' => (int) ($row->breach_count ?? 0),
        ])->values()->all();
    }
}
