<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Policies;

use App\Models\User;
use App\Policies\BaseErpPolicy;
use Modules\Helpdesk\Models\Ticket;

/**
 * Ticket authorization policy with role-based access control.
 *
 * Roles:
 * - reporter: view own tickets
 * - support-agent: view/update assigned tickets
 * - supervisor: view/update all, approve responses
 * - manager: full access, SLA configuration
 * - admin/super-admin: bypass all checks
 */
class TicketPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'reporter_id';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        // Admin/manager/supervisor can view all
        if ($user->hasAnyRole(['super-admin', 'admin', 'manager', 'supervisor'])) {
            return true;
        }

        // Support agent can view assigned tickets
        if ($user->hasRole('support-agent') && $ticket->assignee_id === $user->id) {
            return true;
        }

        // Reporter can view their own tickets
        if ($ticket->reporter_id === $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        // Admin/manager can update any ticket
        if ($user->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            return true;
        }

        // Supervisor can update any ticket
        if ($user->hasRole('supervisor')) {
            return true;
        }

        // Support agent can update assigned tickets only
        if ($user->hasRole('support-agent') && $ticket->assignee_id === $user->id) {
            return true;
        }

        // Reporter can update their own open tickets
        if ($ticket->reporter_id === $user->id && $ticket->status === 'open') {
            return true;
        }

        return false;
    }

    public function closeTicket(User $user, Ticket $ticket): bool
    {
        // Only supervisor, manager, or admin can close tickets
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'supervisor']);
    }

    public function approveResponse(User $user, Ticket $ticket): bool
    {
        // Only supervisor or manager can approve responses
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'supervisor']);
    }

    public function assignTicket(User $user, Ticket $ticket): bool
    {
        // Only supervisor, manager, or admin can assign tickets
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'supervisor']);
    }

    public function manageSla(User $user): bool
    {
        // Only manager and admin can manage SLA policies
        return $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        // Only admin and manager can delete tickets
        return $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }
}
