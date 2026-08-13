<?php

namespace Modules\Validation\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
        ]);
    }

    public function getNextApprovers(ApprovalRequest $request): Collection
    {
        // Get the next level of approvers based on workflow rules
        // This is a simplified implementation
        $rules = $request->workflow->getRulesByOrder();

        if ($rules->isEmpty()) {
            return User::whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })->get();
        }

        return collect();
    }

    public function submitApprovalRequest(ApprovalRequest $request): void
    {
        $request->update(['status' => 'pending']);

        event(new ApprovalRequestCreated($request, $request->workflow));
    }

    public function approveRequest(
        ApprovalRequest $request,
        User $approver,
        ?string $comment = null
    ): void {
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

    public function delegateApproval(ApprovalAction $action, User $delegateTo): void
    {
        ApprovalAction::create([
            'request_id' => $action->request_id,
            'approver_id' => $delegateTo->id,
            'action' => 'delegated',
            'comment' => "Delegated from {$action->approver->name}",
            'acted_at' => now(),
        ]);
    }

    public function getPendingApprovalsForUser(User $user): Collection
    {
        return ApprovalRequest::where('status', 'pending')
            ->with('approvable')
            ->get()
            ->filter(function ($request) use ($user) {
                // Check if user is an approver for this request
                return $request->actions()->where('approver_id', $user->id)->doesntExist();
            });
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
