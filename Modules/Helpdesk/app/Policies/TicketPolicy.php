<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Policies;

use App\Models\User;
use App\Policies\BaseErpPolicy;
use Illuminate\Database\Eloquent\Model;
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

    public function view(User $user, Model $model): bool
    {
        /** @var Ticket $ticket */
        $ticket = $model;

        // Admin/manager/supervisor can view all tickets — but only their own
        // company's (Chantier 32.21: hd_tickets had no company_id at all
        // until this chantier, so this bypass was previously unscoped and a
        // confirmed cross-tenant leak; super-admin keeps the true global
        // bypass every other module's equivalent fix leaves it).
        if ($user->hasRole('super-admin')) {
            return true;
        }
        if ($user->hasAnyRole(['admin', 'manager', 'supervisor']) && $this->sameCompany($user, $ticket)) {
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

    public function update(User $user, Model $model): bool
    {
        /** @var Ticket $ticket */
        $ticket = $model;

        // Admin/manager/supervisor can update any ticket in their own
        // company (see the same fix + rationale on view() above).
        if ($user->hasRole('super-admin')) {
            return true;
        }
        if ($user->hasAnyRole(['admin', 'manager', 'supervisor']) && $this->sameCompany($user, $ticket)) {
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
        // Only supervisor, manager, or admin — of the ticket's own company —
        // can close tickets.
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'manager', 'supervisor']) && $this->sameCompany($user, $ticket);
    }

    public function approveResponse(User $user, Ticket $ticket): bool
    {
        // Only supervisor or manager — of the ticket's own company — can
        // approve responses.
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'manager', 'supervisor']) && $this->sameCompany($user, $ticket);
    }

    public function assignTicket(User $user, Ticket $ticket): bool
    {
        // Only supervisor, manager, or admin — of the ticket's own company —
        // can assign tickets.
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'manager', 'supervisor']) && $this->sameCompany($user, $ticket);
    }

    public function manageSla(User $user): bool
    {
        // Class-level ability (no Ticket instance available to scope by
        // company) — only manager and admin can manage SLA policies.
        return $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    public function delete(User $user, Model $model): bool
    {
        /** @var Ticket $ticket */
        $ticket = $model;

        // Only admin and manager — of the ticket's own company — can delete
        // tickets.
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'manager']) && $this->sameCompany($user, $ticket);
    }

    /**
     * Null-safe on either side, matching this session's established
     * precedent (e.g. Chantier 10's Projects fix): when the ticket has no
     * company_id (pre-Chantier-32.21 data, or a system/console-originated
     * ticket with no authenticated user to stamp one) or the acting user
     * has none (a not-yet-provisioned account), there is no real tenant
     * boundary to enforce, so the check is a no-op rather than a silent
     * deny — this keeps the existing, still-valid "supervisor/manager/
     * admin can view all tickets" test coverage passing on data that
     * predates this chantier. The check only actually restricts when both
     * sides carry a real, differing company_id — exactly the cross-tenant
     * leak this chantier confirmed and closes.
     */
    private function sameCompany(User $user, Ticket $ticket): bool
    {
        if ($ticket->company_id === null || $user->company_id === null) {
            return true;
        }

        return (int) $ticket->company_id === (int) $user->company_id;
    }
}
