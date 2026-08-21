<?php

namespace Modules\Validation\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Validation\Events\ApprovalApproved;
use Modules\Validation\Events\ApprovalCompleted;
use Modules\Validation\Events\ApprovalRejected;
use Modules\Validation\Events\ApprovalRequestCreated;
use Modules\Validation\Models\ApprovalAction;
use Modules\Validation\Models\ApprovalHistory;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;

class ApprovalRequestService
{
    public function createApprovalRequest(
        $approvable,
        ApprovalWorkflow $workflow,
        User $requestedBy
    ): ApprovalRequest {
        return ApprovalRequest::create([
            'workflow_id' => $workflow->id,
            'approvable_type' => get_class($approvable),
            'approvable_id' => $approvable->id,
            'status' => 'pending',
            'requested_by' => $requestedBy->id,
            // Chantier 31: the real tenant boundary for this request — never
            // client-controlled, always derived from the actual requester,
            // matching this session's established company_id-from-the-real-
            // acting-user pattern (never a phantom column, never a header).
            'company_id' => $requestedBy->company_id,
        ]);
    }

    /**
     * Return type was Eloquent\Collection, but ApprovalRoutingResolver::
     * resolveApprovers() has always built and returned a plain
     * Support\Collection (collect()->unique('id')->values()) — a dormant
     * TypeError nothing had actually exercised until approveRequest() below
     * became the first real caller of this method.
     */
    public function getNextApprovers(ApprovalRequest $request): SupportCollection
    {
        return app(ApprovalRoutingResolver::class)->resolveApprovers($request);
    }

    public function submitApprovalRequest(ApprovalRequest $request): void
    {
        $request->update(['status' => 'pending']);

        event(new ApprovalRequestCreated($request, $request->workflow));
    }

    /**
     * Previously always finalized status='approved' on the very first
     * decision, regardless of total_levels — a real routing bug for Achats
     * POs ≥ 50K XOF, which are routed through 3 real approver levels
     * (ApprovalRoutingService::createDefaultWorkflows()) but were approved
     * in full the moment the 1st approver acted, silently skipping levels
     * 2 and 3. `total_levels ?: 1` keeps single-level workflows (invoices,
     * HR leaves — anything that never populates total_levels via
     * ApprovalRoutingResolver) finalizing on the first decision exactly as
     * before.
     */
    public function approveRequest(
        ApprovalRequest $request,
        User $approver,
        ?string $comment = null
    ): void {
        $totalLevels = $request->total_levels ?: 1;
        $currentLevel = $request->current_level ?: 1;

        if ($currentLevel < $totalLevels) {
            $request->recordLevelApproval($approver, $comment, 'pending');
            $request->update(['current_level' => $currentLevel + 1]);

            $nextApprovers = $this->getNextApprovers($request->fresh());
            if ($nextApprovers->isNotEmpty()) {
                $request->update(['approver_id' => $nextApprovers->first()->id]);
            }

            event(new ApprovalApproved($request, $request->actions()->latest()->first(), $approver));

            return;
        }

        $request->approve($approver, $comment);

        event(new ApprovalApproved($request, $request->actions()->latest()->first(), $approver));
    }

    public function rejectRequest(
        ApprovalRequest $request,
        User $approver,
        string $reason
    ): void {
        $request->reject($approver, $reason);

        event(new ApprovalRejected($request, $reason, $approver));
    }

    /**
     * Delegate an approval request to another user. Reassigns approver_id so
     * the delegate can actually act on it (ApprovalRequestPolicy checks
     * approver_id === auth user) — a prior version of this method only wrote
     * an ApprovalAction log entry without reassigning, so the delegate could
     * never actually approve/reject.
     */
    public function delegateApproval(
        ApprovalRequest $request,
        User $from,
        User $to,
        ?string $reason = null
    ): void {
        $request->update([
            'approver_id' => $to->id,
            'escalated_from_id' => $from->id,
            'escalation_reason' => 'manual_delegation',
        ]);

        ApprovalAction::create([
            'request_id' => $request->id,
            'approver_id' => $to->id,
            'action' => 'delegated',
            'comment' => $reason ?? "Delegated from {$from->name}",
            'acted_at' => now(),
        ]);

        ApprovalHistory::create([
            'request_id' => $request->id,
            'level' => $request->current_level,
            'action' => 'delegated',
            'old_status' => $request->status,
            'new_status' => $request->status,
            'changed_by' => $from->id,
            'changed_at' => now(),
        ]);
    }

    public function getPendingApprovalsForUser(User $user): Collection
    {
        // Chantier 8.5sv: was filtering to requests the user HASN'T already
        // acted on (doesntExist() on their own actions) instead of requests
        // where they ARE the assigned approver — since almost nobody has
        // acted on any given pending request yet, this returned every
        // pending approval company-wide to every caller, not just their own.
        return ApprovalRequest::where('status', 'pending')
            ->where('approver_id', $user->id)
            ->with('approvable')
            ->get();
    }

    public function getHistoryForRequest(ApprovalRequest $request): Collection
    {
        return $request->history()->orderBy('changed_at', 'desc')->get();
    }

    public function completeApproval(ApprovalRequest $request): void
    {
        if ($request->isApproved()) {
            $request->markCompleted();

            event(new ApprovalCompleted($request, true));
        }
    }

    public function cancelApprovalRequest(ApprovalRequest $request, string $reason): void
    {
        $request->update(['status' => 'cancelled']);

        ApprovalHistory::create([
            'request_id' => $request->id,
            'level' => $request->current_level,
            'action' => 'cancelled',
            'old_status' => 'pending',
            'new_status' => 'cancelled',
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    public function isApprovalOverdue(ApprovalRequest $request): bool
    {
        $timeoutDays = config('validation.approval_timeout_days', 14);
        $dueDate = $request->created_at->addDays($timeoutDays);

        return now()->isAfter($dueDate) && $request->isPending();
    }
}
