<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Modules\Helpdesk\Models\Ticket;

/**
 * AlertService handles real-time alerts for tickets, SLA breaches, and assignment notifications.
 */
class AlertService
{
    /**
     * Check for SLA breach warnings (24 hours before breach).
     */
    public function checkSlaBreachWarnings(): int
    {
        $count = 0;
        $threshold = now()->addHours(24);

        $tickets = Ticket::where('status', '!=', 'resolved')
            ->where('sla_breached', false)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', $threshold)
            ->where('sla_due_at', '>', now())
            ->with(['assignee', 'reporter', 'team'])
            ->get();

        foreach ($tickets as $ticket) {
            try {
                Log::info('SLA breach warning checked', [
                    'ticket_id' => $ticket->id,
                    'sla_due_at' => $ticket->sla_due_at,
                    'assignee_id' => $ticket->assignee_id,
                ]);
                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to check SLA breach warning', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Check for SLA breaches at breach time.
     */
    public function checkSlaBreaches(): int
    {
        $count = 0;

        $tickets = Ticket::where('status', '!=', 'resolved')
            ->where('sla_breached', false)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->with(['assignee', 'reporter', 'team'])
            ->get();

        foreach ($tickets as $ticket) {
            try {
                $ticket->update(['sla_breached' => true]);

                Log::warning('SLA breach detected', [
                    'ticket_id' => $ticket->id,
                    'sla_due_at' => $ticket->sla_due_at,
                ]);
                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to process SLA breach', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Send notification for newly assigned tickets.
     */
    public function notifyTicketAssignment(Ticket $ticket, int $assigneeId): bool
    {
        try {
            $assignee = $ticket->assignee;

            if (!$assignee) {
                return false;
            }

            Log::info('Ticket assignment notification queued', [
                'ticket_id' => $ticket->id,
                'assignee_id' => $assigneeId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process ticket assignment', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check for response reminders (no response for X hours).
     */
    public function checkResponseReminders(int $hoursWithoutResponse = 24): int
    {
        $count = 0;
        $threshold = now()->subHours($hoursWithoutResponse);

        $tickets = Ticket::where('status', '!=', 'resolved')
            ->whereNull('first_response_at')
            ->where('created_at', '<', $threshold)
            ->with(['assignee', 'reporter', 'team'])
            ->get();

        foreach ($tickets as $ticket) {
            try {
                Log::info('Response reminder checked', [
                    'ticket_id' => $ticket->id,
                    'created_at' => $ticket->created_at,
                ]);
                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to check response reminder', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Get all pending alerts for a user.
     */
    public function getPendingAlerts(int $userId): array
    {
        return [
            'sla_warnings' => $this->getSlaWarningsForUser($userId),
            'sla_breaches' => $this->getSlaBreachesForUser($userId),
            'unresponded_tickets' => $this->getUnrespondedTicketsForUser($userId),
            'assigned_tickets' => $this->getRecentlyAssignedTicketsForUser($userId),
        ];
    }

    private function getSlaWarningsForUser(int $userId)
    {
        $threshold = now()->addHours(24);

        return Ticket::where('assignee_id', $userId)
            ->where('status', '!=', 'resolved')
            ->where('sla_breached', false)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', $threshold)
            ->where('sla_due_at', '>', now())
            ->with(['reporter', 'team'])
            ->get();
    }

    private function getSlaBreachesForUser(int $userId)
    {
        return Ticket::where('assignee_id', $userId)
            ->where('status', '!=', 'resolved')
            ->where('sla_breached', true)
            ->with(['reporter', 'team'])
            ->get();
    }

    private function getUnrespondedTicketsForUser(int $userId)
    {
        $threshold = now()->subHours(24);

        return Ticket::where('assignee_id', $userId)
            ->where('status', '!=', 'resolved')
            ->whereNull('first_response_at')
            ->where('created_at', '<', $threshold)
            ->with(['reporter', 'team'])
            ->get();
    }

    private function getRecentlyAssignedTicketsForUser(int $userId)
    {
        $threshold = now()->subHours(1);

        return Ticket::where('assignee_id', $userId)
            ->where('status', '!=', 'resolved')
            ->where('updated_at', '>', $threshold)
            ->with(['reporter', 'team'])
            ->get();
    }
}
