<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Http\Resources\LeaveRequestResource;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Services\HRService;

/**
 * @group HR - Leave
 *
 * Submit, approve and reject leave requests.
 *
 * Chantier 19 (HR): index()/approve()/update() all eager-loaded/fresh()'d a
 * nonexistent 'approvedBy' relation — LeaveRequest's real relation is
 * approver() — a guaranteed RelationNotFoundException on every real call to
 * 3 of this controller's 6 endpoints, confirmed empirically (this exact
 * page's index() backs HR/Dashboard.vue's own leaves widget and
 * Leaves/Index.vue's admin list, both of which would have fatal'd on
 * every load). Fixed throughout to the real relation name.
 *
 * Chantier 31 (HR re-audit): approve()/reject() — the ones actually wired to
 * Leaves/Index.vue's real approve/reject buttons — wrote
 * $request->user()->id (a users.id) straight into approved_by, but
 * hr_leave_requests.approved_by is a hard FK to hr_employees.id
 * (nullOnDelete). Confirmed empirically via tinker: every real approve
 * click fataled with a SQLSTATE foreign-key-constraint violation unless the
 * approving user's users.id coincidentally also happened to be a valid
 * hr_employees.id — the real, UI-wired approval action was broken for the
 * common case. reject() additionally wrote a 'rejection_reason' key that
 * has never been in LeaveRequest::$fillable (mass-assignment silently
 * dropped it on every call — the column exists on the table, but the typed
 * manager's reason was never actually persisted), and set no approved_by/
 * approved_at at all, so a rejected request carried no record of who
 * rejected it. Both methods now delegate to the already-correct
 * HRService::approveLeave()/rejectLeave() (the same service
 * LeaveRequestController's sibling approve()/reject() already used
 * correctly), resolving the acting user's real Employee id — nullable-safe
 * if the approver has no hr_employees row — and persisting the reason via
 * the model's real 'approval_notes' fillable column instead of the
 * phantom 'rejection_reason' key. This also means the real UI flow now
 * populates hr_leave_approval_log for the first time, matching the
 * LeaveRequestController path's existing behavior.
 */
class LeaveController extends Controller
{
    public function __construct(protected HRService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::with('employee', 'leaveType', 'approver')
            ->when($request->employee_id, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->year, fn ($q, $v) => $q->whereYear('start_date', $v));

        return response()->json($query->latest()->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:hr_employees,id'],
            'leave_type_id' => ['required', 'exists:hr_leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        $days = (int) now()->parse($validated['start_date'])
            ->diffInWeekdays(now()->parse($validated['end_date'])) + 1;

        // Chantier 31: 'days_requested' was never set here — harmless today (nothing
        // reachable reads it; the real leave-balance endpoints sum 'days' instead — see
        // CLAUDE.md), but a real inconsistency vs. LeaveRequestController::store(), which
        // always sets it. Closed rather than left as a landmine for a future reader.
        $request = LeaveRequest::create(array_merge($validated, ['days' => $days, 'days_requested' => $days]));

        return response()->json($request->load('employee', 'leaveType'), 201);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        $user = $request->user();
        $this->service->approveLeave(
            $leaveRequest,
            $user->employee?->id,
            '',
            $user->getRoleNames()->first()
        );

        return response()->json($leaveRequest->fresh('employee', 'leaveType', 'approver'));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        $user = $request->user();
        $this->service->rejectLeave(
            $leaveRequest,
            $user->employee?->id,
            $validated['rejection_reason'],
            $user->getRoleNames()->first()
        );

        return response()->json(new LeaveRequestResource($leaveRequest->fresh()->load('employee', 'leaveType', 'approver')));
    }

    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('update', $leaveRequest);

        $validated = $request->validate([
            'employee_id' => ['sometimes', 'exists:hr_employees,id'],
            'leave_type_id' => ['sometimes', 'exists:hr_leave_types,id'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $start = $validated['start_date'] ?? $leaveRequest->start_date->toDateString();
            $end = $validated['end_date'] ?? $leaveRequest->end_date->toDateString();
            $validated['days'] = (int) now()->parse($start)->diffInWeekdays(now()->parse($end)) + 1;
        }

        $leaveRequest->update($validated);

        return response()->json(new LeaveRequestResource($leaveRequest->fresh()->load('employee', 'leaveType', 'approver')));
    }

    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('delete', $leaveRequest);

        $leaveRequest->delete();

        return response()->json(null, 204);
    }
}
