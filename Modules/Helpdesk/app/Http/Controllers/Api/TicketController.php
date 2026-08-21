<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Events\TicketCreated;
use App\Events\TicketStatusChanged;
use App\Events\TicketUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\TicketAssignmentService;
use Modules\Helpdesk\Services\TicketService;

/**
 * @group Helpdesk - Ticket
 *
 * Create and manage helpdesk support tickets.
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    /**
     * List tickets
     *
     * Returns a paginated list of helpdesk tickets with their team and assignee.
     *
     * @queryParam status string Filter by status (open, pending, resolved, closed). Example: open
     * @queryParam priority string Filter by priority (low, medium, high, urgent). Example: high
     * @queryParam assignee_id integer Filter by assignee user ID. Example: 4
     * @queryParam team_id integer Filter by team ID. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 25
     *
     * @response 200 scenario="Success" {"data": [{"id": 1, "subject": "Login issue", "status": "open", "priority": "high"}], "total": 1, "per_page": 25, "current_page": 1, "last_page": 1}
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['team', 'assignee']);

        // Chantier 32.21: confirmed empirically that any employee of any
        // company (the module:Helpdesk gate is broad by design) could list
        // every other company's tickets — hd_tickets had no company_id
        // column at all until this chantier. super-admin keeps the
        // cross-company view every other module's equivalent fix leaves it;
        // a caller whose own company_id is null (not yet provisioned, or
        // most of this test suite's bare User::factory() fixtures) gets no
        // extra filter, matching this session's established null-safe
        // precedent rather than silently returning zero results. Tickets
        // with no company_id of their own (legacy data, or a
        // console/job-originated ticket) stay visible to everyone, same
        // no-real-boundary-to-enforce rationale as TicketPolicy::
        // sameCompany() below.
        if (! $request->user()->hasRole('super-admin') && $request->user()->company_id !== null) {
            $companyId = $request->user()->company_id;
            $query->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->assignee_id);
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        $paginated = $query->paginate(min((int) ($request->per_page ?? 25), 100));

        return response()->json([
            'data' => $paginated->items(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    /**
     * Create ticket
     *
     * Creates a new helpdesk ticket. The reporter is set to the authenticated user,
     * status is initialised to `open`, and the default SLA policy is applied automatically.
     *
     * @bodyParam subject string required Ticket subject / title. Example: Cannot log in to portal
     * @bodyParam description string Detailed description of the issue. Example: I receive a 403 error on login.
     * @bodyParam channel string Originating channel (web, email, whatsapp, phone). Example: web
     * @bodyParam priority string Priority (low, medium, high, urgent). Example: medium
     * @bodyParam team_id integer ID of the support team to assign to. Example: 1
     * @bodyParam type string Ticket type category. Example: bug
     *
     * @response 201 scenario="Created" {"id": 1, "subject": "Cannot log in to portal", "status": "open", "priority": "medium", "reporter_id": 5}
     * @response 422 scenario="Validation error" {"message": "The subject field is required.", "errors": {"subject": ["The subject field is required."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'channel' => ['nullable', 'in:web,email,whatsapp,phone'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'team_id' => ['nullable', 'exists:hd_teams,id'],
            'type' => ['nullable', 'string'],
            // Optional: raise this ticket about a record from another module
            // (e.g. an Accounting invoice, a CRM contact). source_module must
            // be one of the aliases registered in HelpdeskServiceProvider's
            // morph map — arbitrary class names are never accepted from the
            // client.
            'source_module' => ['nullable', 'string', Rule::in(array_keys(Relation::morphMap()))],
            'source_id' => ['nullable', 'required_with:source_module', 'integer'],
        ]);

        $validated['reporter_id'] = $request->user()->id;
        $validated['status'] = 'open';

        $source = null;
        if (! empty($validated['source_module'])) {
            $sourceClass = Relation::getMorphedModel($validated['source_module']);
            $source = $sourceClass::findOrFail($validated['source_id']);
        }
        unset($validated['source_module'], $validated['source_id']);

        $ticket = $this->ticketService->createFromSource($source, $validated);

        TicketCreated::dispatch($ticket);

        return response()->json($ticket, 201);
    }

    /**
     * Get ticket
     *
     * Returns a single helpdesk ticket with team, assignee, reporter and comments.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @response 200 scenario="Success" {"id": 1, "subject": "Cannot log in", "status": "open", "priority": "medium", "team": {}, "assignee": null, "reporter": {}, "comments": []}
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load(['team', 'assignee', 'reporter', 'comments']);

        return response()->json($ticket);
    }

    /**
     * Update ticket
     *
     * Updates an existing helpdesk ticket. All fields are optional (PATCH semantics).
     * Requires ownership (reporter) or admin/manager role.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @bodyParam subject string Ticket subject / title. Example: Cannot log in to portal
     * @bodyParam description string Detailed description. Example: Updated reproduction steps.
     * @bodyParam channel string Originating channel (web, email, whatsapp, phone). Example: email
     * @bodyParam priority string Priority (low, medium, high, urgent). Example: high
     * @bodyParam team_id integer ID of the support team. Example: 2
     * @bodyParam type string Ticket type category. Example: feature_request
     *
     * @response 200 scenario="Updated" {"id": 1, "subject": "Cannot log in to portal", "priority": "high", "status": "open"}
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 422 scenario="Validation error" {"message": "The priority must be one of: low, medium, high, urgent.", "errors": {"priority": ["The priority must be one of: low, medium, high, urgent."]}}
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);
        $validated = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'channel' => ['nullable', 'in:web,email,whatsapp,phone'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'team_id' => ['nullable', 'exists:hd_teams,id'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,pending,resolved,closed'],
            'sla_id' => ['nullable', 'exists:hd_sla_policies,id'],
        ]);

        if (array_key_exists('status', $validated) && in_array($validated['status'], ['resolved', 'closed'], true)) {
            $this->authorize('closeTicket', $ticket);
        }

        if (array_key_exists('sla_id', $validated)) {
            $this->authorize('manageSla', Ticket::class);
        }

        $ticket->update($validated);

        TicketUpdated::dispatch($ticket->fresh());

        return response()->json($ticket);
    }

    /**
     * Delete ticket
     *
     * Permanently deletes a helpdesk ticket. Requires ownership or admin/manager role.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @response 204 scenario="Deleted"
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return response()->json(null, 204);
    }

    /**
     * Assign ticket
     *
     * Assigns a helpdesk ticket to a specific user.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @bodyParam assignee_id integer required ID of the user to assign the ticket to. Example: 3
     *
     * @response 200 scenario="Assigned" {"id": 1, "subject": "Cannot log in", "assignee_id": 3}
     * @response 422 scenario="Validation error" {"message": "The assignee id field is required.", "errors": {"assignee_id": ["The assignee id field is required."]}}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        // Chantier 32.21: this endpoint had zero authorize() call at all —
        // any authenticated user passing the broad module:Helpdesk gate
        // could reassign any ticket, including cross-company, confirmed
        // empirically. TicketPolicy::assignTicket() already existed
        // specifically for this action but was never actually called.
        $this->authorize('assignTicket', $ticket);

        $validated = $request->validate([
            'assignee_id' => ['required', 'exists:users,id'],
        ]);

        $ticket->update($validated);

        return response()->json($ticket);
    }

    /**
     * Auto-assign ticket (round-robin)
     *
     * Assigns a helpdesk ticket to the least-loaded active support agent on
     * its team, using TicketAssignmentService::assignRoundRobin() — a real,
     * tested service that previously had zero real caller anywhere in the
     * app (Chantier 32.21).
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @response 200 scenario="Assigned" {"id": 1, "assignee_id": 3}
     * @response 422 scenario="No team or no available agent" {"message": "No support agent could be auto-assigned (no team, or no active agent available)."}
     */
    public function autoAssign(Ticket $ticket, TicketAssignmentService $assignmentService): JsonResponse
    {
        $this->authorize('assignTicket', $ticket);

        $assignee = $assignmentService->assignRoundRobin($ticket);

        if (! $assignee) {
            return response()->json([
                'message' => 'No support agent could be auto-assigned (no team, or no active agent available).',
            ], 422);
        }

        return response()->json($ticket->fresh());
    }

    /**
     * Resolve ticket
     *
     * Marks a helpdesk ticket as resolved and records the resolution timestamp.
     * Fires a `TicketStatusChanged` event.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @response 200 scenario="Resolved" {"id": 1, "status": "resolved", "resolved_at": "2025-05-04T12:00:00Z"}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function resolve(Ticket $ticket): JsonResponse
    {
        // Chantier 32.21: zero authorize() call — the general update()
        // endpoint already gates the identical resolved/closed status
        // transition behind TicketPolicy::closeTicket(), but this dedicated
        // action endpoint didn't, letting any user close out any ticket.
        $this->authorize('closeTicket', $ticket);

        $old = $ticket->status;
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        TicketStatusChanged::dispatch($ticket, $old);

        return response()->json($ticket);
    }

    /**
     * Close ticket
     *
     * Sets a helpdesk ticket status to `closed`. Fires a `TicketStatusChanged` event.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @response 200 scenario="Closed" {"id": 1, "status": "closed"}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function close(Ticket $ticket): JsonResponse
    {
        $this->authorize('closeTicket', $ticket);

        $old = $ticket->status;
        $ticket->update(['status' => 'closed']);
        TicketStatusChanged::dispatch($ticket, $old);

        return response()->json($ticket);
    }

    /**
     * Escalate ticket
     *
     * Escalates a ticket by setting its priority to `urgent` and marking the SLA as breached.
     * Fires a `TicketStatusChanged` event.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @response 200 scenario="Escalated" {"id": 1, "priority": "urgent", "sla_breached": true}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function escalate(Request $request, Ticket $ticket): JsonResponse
    {
        // Chantier 32.21: escalate() is an update of priority/sla_breached
        // and had zero authorize() call — gated with the same `update`
        // ability the generic PUT endpoint already applies to those same
        // fields (admin/manager/supervisor bypass, assigned support-agent,
        // or the reporter on their own still-open ticket).
        $this->authorize('update', $ticket);

        $old = $ticket->status;
        $ticket->update(['priority' => 'urgent', 'sla_breached' => true]);
        TicketStatusChanged::dispatch($ticket, $old);

        return response()->json($ticket->fresh());
    }
}
