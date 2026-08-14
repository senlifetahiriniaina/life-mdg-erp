<?php

namespace Modules\Validation\Policies;

use App\Models\User;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'approver']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('validation.request.create') || $user->hasAnyRole(['admin', 'manager']);
    }

    public function view(User $user, ApprovalRequest $request): bool
    {
        // User can view if they are the requester, approver, or have admin role
        return $user->id === $request->requested_by
            || $user->id === $request->approver_id
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function approve(User $user, ApprovalRequest $request): bool
    {
        // Only the assigned approver or admin/manager can approve. This
        // intentionally does NOT grant everyone holding the 'approver' role
        // blanket approve access — that role only affects viewAny() (seeing
        // the list). Once ApprovalRoutingResolver (Phase 2) can assign a
        // level to multiple role-holders, this should also allow whichever
        // of them the resolver currently resolves for this request/level.
        if ($request->status !== 'pending') {
            return false;
        }

        return $user->id === $request->approver_id
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function reject(User $user, ApprovalRequest $request): bool
    {
        // Only the assigned approver or admin/manager can reject
        if ($request->status !== 'pending') {
            return false;
        }

        return $user->id === $request->approver_id
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function delegate(User $user, ApprovalRequest $request): bool
    {
        // Only the assigned approver or admin/manager can delegate
        if ($request->status !== 'pending') {
            return false;
        }

        return $user->id === $request->approver_id
            || $user->hasAnyRole(['admin', 'manager']);
    }
}
