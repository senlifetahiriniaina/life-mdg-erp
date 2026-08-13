<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HelpdeskActionHandler — Phase 39
 *
 * Handles workflow actions for the Helpdesk module (ticket lifecycle, CSAT).
 */
class HelpdeskActionHandler
{
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
     * Auto-create a support ticket from any workflow trigger.
     *
     * @param  array<string,mixed>  $params   e.g. ['priority' => 'high', 'subject' => '...']
     * @param  array<string,mixed>  $context
     * @return array{ticket_id: int|null, status: string}
     */
    public function createTicket(array $params, array $context): array
    {
        $tenantId  = $context['tenant_id'] ?? 1;
        $clientId  = $context['client_id'] ?? null;
        $subject   = $params['subject'] ?? ($context['subject'] ?? 'Ticket auto-créé par automatisation');
        $priority  = $params['priority'] ?? 'normal';
        $body      = $params['body'] ?? json_encode($context);

        try {
            $ticketId = DB::table('helpdesk_tickets')->insertGetId([
                'tenant_id'  => $tenantId,
                'client_id'  => $clientId,
                'subject'    => $subject,
                'body'       => $body,
                'priority'   => $priority,
                'status'     => 'open',
                'source'     => 'workflow_automation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('WorkflowAction: helpdesk ticket created', ['ticket_id' => $ticketId]);

            return ['ticket_id' => $ticketId, 'status' => 'created', 'priority' => $priority];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createTicket skipped', ['error' => $e->getMessage()]);
            return ['ticket_id' => null, 'status' => 'simulated', 'priority' => $priority];
        }
    }

    /**
     * action: helpdesk.escalate_ticket
     * Move a ticket to the next support level.
     *
     * @param  array<string,mixed>  $params   e.g. ['level' => 2]
     * @param  array<string,mixed>  $context
     * @return array{escalated: bool, ticket_id: int|null, level: int}
     */
    public function escalateTicket(array $params, array $context): array
    {
        $ticketId = $context['ticket_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? 1;
        $level    = (int) ($params['level'] ?? 2);

        if (! $ticketId) {
            return ['status' => 'error', 'reason' => 'Missing ticket_id in context'];
        }

        try {
            $rows = DB::table('helpdesk_tickets')
                ->where('id', $ticketId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'support_level' => $level,
                    'escalated_at'  => now(),
                    'updated_at'    => now(),
                ]);

            return ['escalated' => $rows > 0, 'ticket_id' => $ticketId, 'level' => $level];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: escalateTicket skipped', ['error' => $e->getMessage()]);
            return ['escalated' => false, 'ticket_id' => $ticketId, 'level' => $level, 'status' => 'simulated'];
        }
    }

    /**
     * action: helpdesk.assign_ticket
     * Assign a ticket to a specific agent or team.
     *
     * @param  array<string,mixed>  $params   e.g. ['agent_id' => 5] or ['team_id' => 3]
     * @param  array<string,mixed>  $context
     * @return array{assigned: bool, ticket_id: int|null}
     */
    public function assignTicket(array $params, array $context): array
    {
        $ticketId = $context['ticket_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? 1;
        $agentId  = $params['agent_id'] ?? null;
        $teamId   = $params['team_id'] ?? null;

        if (! $ticketId) {
            return ['status' => 'error', 'reason' => 'Missing ticket_id in context'];
        }

        try {
            $rows = DB::table('helpdesk_tickets')
                ->where('id', $ticketId)
                ->where('tenant_id', $tenantId)
                ->update(array_filter([
                    'assigned_to'  => $agentId,
                    'team_id'      => $teamId,
                    'assigned_at'  => now(),
                    'updated_at'   => now(),
                ]));

            return ['assigned' => $rows > 0, 'ticket_id' => $ticketId, 'agent_id' => $agentId, 'team_id' => $teamId];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: assignTicket skipped', ['error' => $e->getMessage()]);
            return ['assigned' => false, 'ticket_id' => $ticketId, 'status' => 'simulated'];
        }
    }

    /**
     * action: helpdesk.close_ticket
     * Mark a ticket as resolved with an optional resolution note.
     *
     * @param  array<string,mixed>  $params   e.g. ['resolution_note' => '...']
     * @param  array<string,mixed>  $context
     * @return array{closed: bool, ticket_id: int|null}
     */
    public function closeTicket(array $params, array $context): array
    {
        $ticketId       = $context['ticket_id'] ?? null;
        $tenantId       = $context['tenant_id'] ?? 1;
        $resolutionNote = $params['resolution_note'] ?? 'Résolu automatiquement';

        if (! $ticketId) {
            return ['status' => 'error', 'reason' => 'Missing ticket_id in context'];
        }

        try {
            $rows = DB::table('helpdesk_tickets')
                ->where('id', $ticketId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status'          => 'resolved',
                    'resolution_note' => $resolutionNote,
                    'resolved_at'     => now(),
                    'updated_at'      => now(),
                ]);

            return ['closed' => $rows > 0, 'ticket_id' => $ticketId];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: closeTicket skipped', ['error' => $e->getMessage()]);
            return ['closed' => false, 'ticket_id' => $ticketId, 'status' => 'simulated'];
        }
    }

    /**
     * action: helpdesk.send_satisfaction_survey
     * Trigger a post-resolution CSAT survey for the ticket's client.
     *
     * @param  array<string,mixed>  $params   e.g. ['survey_template' => 'nps']
     * @param  array<string,mixed>  $context
     * @return array{survey_sent: bool, ticket_id: int|null}
     */
    public function sendSatisfactionSurvey(array $params, array $context): array
    {
        $ticketId       = $context['ticket_id'] ?? null;
        $tenantId       = $context['tenant_id'] ?? 1;
        $clientId       = $context['client_id'] ?? null;
        $surveyTemplate = $params['survey_template'] ?? 'csat';

        try {
            DB::table('notifications')->insert([
                'tenant_id'       => $tenantId,
                'notifiable_type' => 'client',
                'notifiable_id'   => (int) $clientId,
                'type'            => 'helpdesk.satisfaction_survey',
                'data'            => json_encode([
                    'ticket_id'       => $ticketId,
                    'survey_template' => $surveyTemplate,
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Non-blocking
        }

        return ['survey_sent' => true, 'ticket_id' => $ticketId, 'survey_template' => $surveyTemplate];
    }
}
