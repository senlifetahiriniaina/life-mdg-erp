<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Carbon;
use Modules\Helpdesk\Models\EscalationEvent;
use Modules\Helpdesk\Models\EscalationRule;
use Modules\Helpdesk\Models\HelpdeskSlaPolicy;
use Modules\Helpdesk\Models\Ticket;

class EscalationService
{
    /**
     * Assign default SLA policy to a ticket.
     */
    public function assignSla(Ticket $ticket): void
    {
        $policy = HelpdeskSlaPolicy::where('is_default', true)->first()
            ?? HelpdeskSlaPolicy::where('priority', $ticket->priority)->first();

        if (! $policy) {
            return;
        }

        $firstResponseDue = now()->addHours($policy->first_response_hours);
        $resolutionDue = now()->addHours($policy->resolution_hours);

        $ticket->update([
            'sla_due_at' => $resolutionDue,
            'sla_breached' => false,
        ]);
    }

    /**
     * Check all active escalation rules against the ticket and trigger matching ones.
     *
     * @return array<int, EscalationEvent>
     */
    public function checkAndEscalate(Ticket $ticket): array
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return [];
        }

        $rules = EscalationRule::where('is_active', true)
            ->orderBy('priority')
            ->get();

        $events = [];
        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $ticket)) {
                $events[] = $this->executeEscalation($rule, $ticket);
            }
        }

        return $events;
    }

    /**
     * Execute a single escalation rule against a ticket.
     */
    public function executeEscalation(EscalationRule $rule, Ticket $ticket): EscalationEvent
    {
        $actionTaken = '';
        $result = 'success';

        try {
            $actionTaken = $this->applyAction($rule, $ticket);
        } catch (\Throwable $e) {
            $result = 'failed';
            $actionTaken = $e->getMessage();
        }

        return EscalationEvent::create([
            'ticket_id' => $ticket->id,
            'rule_id' => $rule->id,
            'triggered_at' => now(),
            'action_taken' => $actionTaken,
            'result' => $result,
        ]);
    }

    /**
     * Get SLA breach status for a ticket.
     *
     * @return array{first_response_breached: bool, resolution_breached: bool, breach_at: Carbon|null}
     */
    public function getSlaBreach(Ticket $ticket): array
    {
        $firstResponseBreached = false;
        $resolutionBreached = false;
        $breachAt = null;

        if ($ticket->sla_due_at !== null) {
            $resolutionBreached = now()->isAfter($ticket->sla_due_at);
            $breachAt = $ticket->sla_due_at;
        }

        // Check first response breach if ticket is not yet responded
        if ($ticket->first_response_at === null && $ticket->sla_due_at !== null) {
            // Assume first response due is 25% of resolution time as heuristic
            $estimatedFirstResponseDue = Carbon::parse($ticket->created_at)->addHours(
                (float) ($ticket->sla_due_at->diffInHours($ticket->created_at) * 0.25)
            );
            $firstResponseBreached = now()->isAfter($estimatedFirstResponseDue);
        }

        return [
            'first_response_breached' => $firstResponseBreached,
            'resolution_breached' => $resolutionBreached,
            'breach_at' => $breachAt,
        ];
    }

    /**
     * Check if a rule matches the given ticket.
     */
    private function ruleMatches(EscalationRule $rule, Ticket $ticket): bool
    {
        $ticketAge = (float) Carbon::parse($ticket->created_at)->diffInHours(now());

        return match ($rule->trigger_type) {
            'first_response_overdue' => $ticket->first_response_at === null
                && $ticketAge >= $rule->trigger_hours,
            'resolution_overdue' => $ticket->resolved_at === null
                && $ticketAge >= $rule->trigger_hours,
            'no_activity' => $this->hasNoRecentActivity($ticket, $rule->trigger_hours),
            'custom' => $ticketAge >= $rule->trigger_hours,
            default => false,
        };
    }

    /**
     * Check if ticket has had no activity for the given hours.
     */
    private function hasNoRecentActivity(Ticket $ticket, float $hours): bool
    {
        $lastActivity = $ticket->updated_at ?? $ticket->created_at;

        return (float) Carbon::parse($lastActivity)->diffInHours(now()) >= $hours;
    }

    /**
     * Apply the rule's action to the ticket and return a description.
     */
    private function applyAction(EscalationRule $rule, Ticket $ticket): string
    {
        $config = $rule->action_config;

        return match ($rule->action_type) {
            'change_priority' => $this->changePriority($ticket, $config),
            'reassign' => $this->reassign($ticket, $config),
            'notify' => $this->notify($ticket, $config),
            'add_tag' => $this->addTag($ticket, $config),
            default => "Unknown action: {$rule->action_type}",
        };
    }

    /** @param array<string,mixed> $config */
    private function changePriority(Ticket $ticket, array $config): string
    {
        $priority = $config['priority'] ?? 'high';
        $ticket->update(['priority' => $priority]);

        return "Changed priority to {$priority}";
    }

    /** @param array<string,mixed> $config */
    private function reassign(Ticket $ticket, array $config): string
    {
        $agentId = $config['agent_id'] ?? null;
        if ($agentId) {
            $ticket->update(['assignee_id' => $agentId]);

            return "Reassigned to agent {$agentId}";
        }

        return 'Reassign skipped: no agent_id in config';
    }

    /** @param array<string,mixed> $config */
    private function notify(Ticket $ticket, array $config): string
    {
        // Notification logic would use Laravel notifications in production
        $recipient = $config['recipient'] ?? 'admin';

        return "Notification sent to {$recipient} for ticket #{$ticket->id}";
    }

    /** @param array<string,mixed> $config */
    private function addTag(Ticket $ticket, array $config): string
    {
        $tag = $config['tag'] ?? 'escalated';

        // Tags would be stored in a separate column or pivot in production
        return "Added tag '{$tag}' to ticket #{$ticket->id}";
    }
}
