<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Helpdesk\Models\Ticket;

class TicketPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'reporter_id';

    /**
     * Determine if the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Super admin and admin can view all
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Reporter can view their own ticket
        if ($ticket->reporter_id === $user->id) {
            return true;
        }

        // Assigned agent can view their assigned tickets
        if ($ticket->assignee_id === $user->id && $user->hasRole('helpdesk-agent')) {
            return true;
        }

        // Helpdesk supervisor/manager can view all assigned to their team
        if ($user->hasAnyRole(['helpdesk-supervisor', 'helpdesk-manager'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        // Super admin and admin can update all
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Only assigned agent can update (not reporter)
        if ($ticket->assignee_id === $user->id && $user->hasRole('helpdesk-agent')) {
            return true;
        }

        // Supervisor can update all assigned to their team
        if ($user->hasAnyRole(['helpdesk-supervisor', 'helpdesk-manager'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can close/resolve the ticket.
     * Only supervisors and higher can close tickets.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        // Super admin and admin can close all
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Only supervisor and manager can close
        if ($user->hasAnyRole(['helpdesk-supervisor', 'helpdesk-manager'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can reassign the ticket.
     */
    public function reassign(User $user, Ticket $ticket): bool
    {
        // Super admin and admin can reassign all
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Supervisor and manager can reassign
        if ($user->hasAnyRole(['helpdesk-supervisor', 'helpdesk-manager'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        // Only admin and super-admin can delete
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can approve responses.
     */
    public function approveResponse(User $user, Ticket $ticket): bool
    {
        // Only supervisor and manager can approve
        if ($user->hasAnyRole(['super-admin', 'admin', 'helpdesk-supervisor', 'helpdesk-manager'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can configure SLAs.
     */
    public function configureSla(User $user): bool
    {
        // Only manager-level can configure SLAs
        if ($user->hasAnyRole(['super-admin', 'admin', 'helpdesk-manager'])) {
            return true;
        }

        return false;
    }
}

