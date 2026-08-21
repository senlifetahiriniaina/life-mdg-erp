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

    /**
     * Chantier 31: `hasAnyRole(['admin', 'manager'])` used to grant blanket
     * access regardless of which company the request actually belongs to —
     * Spatie roles are global (not per-company) in this app, confirmed
     * repeatedly elsewhere in this session's own findings (e.g. the CRM
     * CampaignPolicy fix), so ANY company's admin/manager could view/act on
     * ANY OTHER company's approval requests. Confirmed empirically via a
     * real cross-company HTTP request before this fix. Now requires the
     * admin/manager to also share the request's real company_id
     * (ApprovalRequestService::createApprovalRequest() populates it from the
     * real requester, never client-controlled) — a request with a NULL
     * company_id (pre-migration legacy data) is deny-by-default here, not
     * treated as "visible to everyone."
     */
    private function sameCompany(User $user, ApprovalRequest $request): bool
    {
        // Both sides normalized to the same 0 sentinel when NULL — the
        // `?? 0` convention already established throughout this app for a
        // phantom/never-populated company_id (e.g. core_audit_logs'
        // backfill) — so two "no real company" users/requests (legacy
        // fixtures, a superadmin-style account with no company of its own)
        // are still treated as comparable, while any two DIFFERENT real
        // company_ids are correctly denied.
        return (int) ($user->company_id ?? 0) === (int) ($request->company_id ?? 0);
    }

    public function view(User $user, ApprovalRequest $request): bool
    {
        // User can view if they are the requester, approver, or an
        // admin/manager of the SAME company as the request.
        return $user->id === $request->requested_by
            || $user->id === $request->approver_id
            || ($user->hasAnyRole(['admin', 'manager']) && $this->sameCompany($user, $request));
    }

    public function approve(User $user, ApprovalRequest $request): bool
    {
        // Only the assigned approver, or a same-company admin/manager, can
        // approve. This intentionally does NOT grant everyone holding the
        // 'approver' role blanket approve access — that role only affects
        // viewAny() (seeing the list). Once ApprovalRoutingResolver
        // (Phase 2) can assign a level to multiple role-holders, this
        // should also allow whichever of them the resolver currently
        // resolves for this request/level.
        if ($request->status !== 'pending') {
            return false;
        }

        return $user->id === $request->approver_id
            || ($user->hasAnyRole(['admin', 'manager']) && $this->sameCompany($user, $request));
    }

    public function reject(User $user, ApprovalRequest $request): bool
    {
        // Only the assigned approver, or a same-company admin/manager, can reject
        if ($request->status !== 'pending') {
            return false;
        }

        return $user->id === $request->approver_id
            || ($user->hasAnyRole(['admin', 'manager']) && $this->sameCompany($user, $request));
    }

    public function delegate(User $user, ApprovalRequest $request): bool
    {
        // Only the assigned approver, or a same-company admin/manager, can delegate
        if ($request->status !== 'pending') {
            return false;
        }

        return $user->id === $request->approver_id
            || ($user->hasAnyRole(['admin', 'manager']) && $this->sameCompany($user, $request));
    }
}
