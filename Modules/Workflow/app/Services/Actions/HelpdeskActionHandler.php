<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\Log;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\EscalationService;
use Modules\Helpdesk\Services\TicketAssignmentService;
use Modules\Helpdesk\Services\TicketService;

/**
 * HelpdeskActionHandler — Phase 39
 *
 * Handles workflow actions for the Helpdesk module (ticket lifecycle, CSAT).
 *
 * Rewritten to use the real `hd_tickets` schema via the Ticket Eloquent
 * model and the existing Helpdesk services (TicketService, EscalationService)
 * instead of raw DB::table('helpdesk_tickets', ...) writes — the previous
 * implementation targeted a table name and column set (helpdesk_tickets,
 * tenant_id, client_id, body, support_level, assigned_to, resolution_note)
 * that never matched the actual hd_tickets schema (ticket_number, reporter_id,
 * assignee_id, description, ...), so every call silently fell into the
 * catch-all "simulated" branch and never touched real data.
 */
class HelpdeskActionHandler
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly EscalationService $escalationService,
        private readonly TicketAssignmentService $assignmentService,
    ) {}

    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'helpdesk.create_ticket'              => $this->createTicket($params, $context),
            'helpdesk.escalate_ticket'            => $this->escalateTicket($params, $context),
            'helpdesk.assign_ticket'              => $this->assignTicket($params, $context),
            'helpdesk.close_ticket'               => $this->closeTicket($params, $context),
            'helpdesk.send_satisfaction_survey'   => $this->sendSatisfactionSurvey($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Helpdesk action: {$action}"],
        };
    }

    /**
     * action: helpdesk.create_ticket
     * Auto-create a support ticket from any workflow trigger. When the
     * triggering context carries a source module/record (e.g. an Accounting
     * invoice, a CRM contact), the ticket is linked to it via the generic
     * source_type/source_id polymorphic columns.
     *
     * @param  array<string,mixed>  $params   e.g. ['priority' => 'high', 'subject' => '...']
     * @param  array<string,mixed>  $context
     * @return array{ticket_id: int|null, status: string}
     */
    public function createTicket(array $params, array $context): array
    {
        $subject = $params['subject'] ?? ($context['subject'] ?? 'Ticket auto-créé par automatisation');
        $priority = $params['priority'] ?? 'medium';

        try {
            $source = $context['source'] ?? null;
            $source = $source instanceof \Illuminate\Database\Eloquent\Model ? $source : null;

            $ticket = $this->ticketService->createFromSource(
                source: $source,
                data: [
                    'subject' => $subject,
                    'description' => $params['body'] ?? json_encode($context),
                    'priority' => $priority,
                    'type' => $params['type'] ?? null,
                    'reporter_id' => $context['user_id'] ?? null,
                    'customer_id' => $context['client_id'] ?? null,
                    'source_ref' => 'workflow_automation',
                ],
            );

            Log::info('WorkflowAction: helpdesk ticket created', ['ticket_id' => $ticket->id]);

            return ['ticket_id' => $ticket->id, 'status' => 'created', 'priority' => $priority];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createTicket failed', ['error' => $e->getMessage()]);

            return ['ticket_id' => null, 'status' => 'error', 'priority' => $priority, 'reason' => $e->getMessage()];
        }
    }

    /**
     * action: helpdesk.escalate_ticket
     * Run the ticket through the real escalation rule engine.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array{escalated: bool, ticket_id: int|null}
     */
    public function escalateTicket(array $params, array $context): array
    {
        $ticketId = $context['ticket_id'] ?? null;

        if (! $ticketId) {
            return ['status' => 'error', 'reason' => 'Missing ticket_id in context'];
        }

        $ticket = Ticket::find($ticketId);
        if (! $ticket) {
            return ['status' => 'error', 'reason' => "Ticket {$ticketId} not found"];
        }

        $events = $this->escalationService->checkAndEscalate($ticket);

        return ['escalated' => count($events) > 0, 'ticket_id' => $ticketId, 'events' => count($events)];
    }

    /**
     * action: helpdesk.assign_ticket
     * Assign a ticket to a specific agent, or round-robin within a team.
     *
     * @param  array<string,mixed>  $params   e.g. ['agent_id' => 5] or ['team_id' => 3]
     * @param  array<string,mixed>  $context
     * @return array{assigned: bool, ticket_id: int|null}
     */
    public function assignTicket(array $params, array $context): array
    {
        $ticketId = $context['ticket_id'] ?? null;
        $agentId = $params['agent_id'] ?? null;

        if (! $ticketId) {
            return ['status' => 'error', 'reason' => 'Missing ticket_id in context'];
        }

        $ticket = Ticket::find($ticketId);
        if (! $ticket) {
            return ['status' => 'error', 'reason' => "Ticket {$ticketId} not found"];
        }

        if (isset($params['team_id'])) {
            $ticket->team_id = $params['team_id'];
        }

        if ($agentId) {
            $ticket->assignee_id = $agentId;
            $ticket->save();
        } else {
            $this->assignmentService->assignRoundRobin($ticket);
        }

        return ['assigned' => (bool) $ticket->fresh()->assignee_id, 'ticket_id' => $ticketId];
    }

    /**
     * action: helpdesk.close_ticket
     * Mark a ticket as resolved.
     *
     * @param  array<string,mixed>  $params   e.g. ['resolution_note' => '...']
     * @param  array<string,mixed>  $context
     * @return array{closed: bool, ticket_id: int|null}
     */
    public function closeTicket(array $params, array $context): array
    {
        $ticketId = $context['ticket_id'] ?? null;

        if (! $ticketId) {
            return ['status' => 'error', 'reason' => 'Missing ticket_id in context'];
        }

        $ticket = Ticket::find($ticketId);
        if (! $ticket) {
            return ['status' => 'error', 'reason' => "Ticket {$ticketId} not found"];
        }

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return ['closed' => true, 'ticket_id' => $ticketId];
    }

    /**
     * action: helpdesk.send_satisfaction_survey
     * Trigger a post-resolution CSAT survey for the ticket's reporter.
     *
     * @param  array<string,mixed>  $params   e.g. ['survey_template' => 'nps']
     * @param  array<string,mixed>  $context
     * @return array{survey_sent: bool, ticket_id: int|null}
     */
    public function sendSatisfactionSurvey(array $params, array $context): array
    {
        $ticketId = $context['ticket_id'] ?? null;
        $surveyTemplate = $params['survey_template'] ?? 'csat';

        $ticket = $ticketId ? Ticket::find($ticketId) : null;

        if ($ticket?->reporter_id) {
            try {
                \DB::table('notifications')->insert([
                    'notifiable_type' => \App\Models\User::class,
                    'notifiable_id' => $ticket->reporter_id,
                    'type' => 'helpdesk.satisfaction_survey',
                    'data' => json_encode([
                        'ticket_id' => $ticketId,
                        'survey_template' => $surveyTemplate,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {
                // Non-blocking
            }
        }

        return ['survey_sent' => (bool) $ticket, 'ticket_id' => $ticketId, 'survey_template' => $surveyTemplate];
    }
}
